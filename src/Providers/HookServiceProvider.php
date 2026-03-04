<?php

namespace FriendsOfBotble\TikTokPixel\Providers;

use Botble\Ecommerce\Events\OrderPlacedEvent;
use Botble\Ecommerce\Events\ProductViewed;
use Botble\Ecommerce\Facades\Cart;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\Product;
use FriendsOfBotble\TikTokPixel\Services\TikTokPixelService;
use Illuminate\Support\ServiceProvider;

class HookServiceProvider extends ServiceProvider
{
    /** @var array<int|string, mixed> */
    protected array $resolvedProducts = [];

    public function boot(): void
    {
        $service = $this->app->make(TikTokPixelService::class);

        if (! $service->isEnabled()) {
            return;
        }

        if (! defined('THEME_FRONT_HEADER') || ! defined('THEME_FRONT_FOOTER')) {
            return;
        }

        add_filter(THEME_FRONT_HEADER, function (?string $html) use ($service): string {
            if (is_in_admin()) {
                return (string) $html;
            }

            return (string) $html . $this->renderPixelScript($service);
        }, 15);

        add_filter(THEME_FRONT_FOOTER, function (?string $html) use ($service): string {
            if (is_in_admin()) {
                return (string) $html;
            }

            return (string) $html . $this->renderEventScripts($service);
        }, 998);

        if ($this->isEcommerceActive()) {
            $this->registerEcommerceHooks($service);
            $this->registerServerSideHooks($service);
        }

        $this->detectSearchEvent($service);
    }

    protected function isEcommerceActive(): bool
    {
        return function_exists('is_plugin_active') && is_plugin_active('ecommerce');
    }

    protected function renderPixelScript(TikTokPixelService $service): string
    {
        $identifyData = null;

        if ($this->isEcommerceActive() && class_exists(Customer::class)) {
            $customer = auth('customer')->user();

            if ($customer) {
                $identifyData = array_filter([
                    'email' => $service->hashValue($customer->email),
                    'phone_number' => $customer->phone ? $service->hashValue($customer->phone) : null,
                    'external_id' => $service->hashValue((string) $customer->getKey()),
                ]);
            }
        }

        return view('plugins/fob-tiktok-pixel::partials.pixel-script', [
            'pixelId' => $service->getPixelId(),
            'trackPageView' => $service->isEventEnabled('page_view'),
            'identifyData' => $identifyData,
        ])->render();
    }

    protected function renderEventScripts(TikTokPixelService $service): string
    {
        $events = $service->getClientEvents();

        if (empty($events)) {
            return '';
        }

        return view('plugins/fob-tiktok-pixel::partials.event-script', [
            'events' => $events,
        ])->render();
    }

    protected function registerEcommerceHooks(TikTokPixelService $service): void
    {
        if (class_exists(ProductViewed::class)) {
            $this->app['events']->listen(
                ProductViewed::class,
                function ($event) use ($service): void {
                    $this->handleProductViewed($event, $service);
                }
            );
        }

        if (function_exists('add_action')) {
            add_action('ecommerce_before_add_to_cart', function ($product) use ($service): void {
                $this->handleAddToCart($product, $service);
            }, 20);

            $this->detectCheckoutEvent($service);
        }

        if (class_exists(OrderPlacedEvent::class)) {
            $this->app['events']->listen(
                OrderPlacedEvent::class,
                function ($event) use ($service): void {
                    $this->handleOrderPlaced($event, $service);
                }
            );
        }
    }

    protected function registerServerSideHooks(TikTokPixelService $service): void
    {
        if (! $service->isEventsApiEnabled()) {
            return;
        }

        if (class_exists(ProductViewed::class)) {
            $this->app['events']->listen(
                ProductViewed::class,
                function ($event) use ($service): void {
                    $this->sendServerViewContent($event, $service);
                }
            );
        }

        if (class_exists(OrderPlacedEvent::class)) {
            $this->app['events']->listen(
                OrderPlacedEvent::class,
                function ($event) use ($service): void {
                    $this->sendServerCompletePayment($event->order, $service);
                }
            );
        }
    }

    protected function handleProductViewed($event, TikTokPixelService $service): void
    {
        $product = $this->resolveProduct($event->productId);

        if (! $product) {
            return;
        }

        $currency = get_application_currency()->title;
        $eventId = $service->generateEventId();

        $service->bufferClientEvent('ViewContent', [
            'content_id' => (string) $product->getKey(),
            'content_type' => 'product',
            'content_name' => $product->name,
            'value' => (float) ($product->front_sale_price ?? $product->price),
            'currency' => $currency,
        ], $eventId);

        session()->put('tiktok_pixel_view_content_event_' . $event->productId, $eventId);
    }

    protected function handleAddToCart($product, TikTokPixelService $service): void
    {
        if (! class_exists(Product::class) || ! $product instanceof Product) {
            return;
        }

        $originalProduct = $product->original_product ?? $product;
        $currency = get_application_currency()->title;
        $quantity = (int) request()->input('qty', 1);
        $price = (float) ($product->front_sale_price ?? $product->price);

        $service->bufferClientEvent('AddToCart', [
            'content_id' => (string) $originalProduct->getKey(),
            'content_type' => 'product',
            'content_name' => $originalProduct->name,
            'quantity' => $quantity,
            'value' => $price * $quantity,
            'currency' => $currency,
        ]);
    }

    protected function detectCheckoutEvent(TikTokPixelService $service): void
    {
        add_filter(THEME_FRONT_FOOTER, function (?string $html) use ($service): ?string {
            if (! request()->routeIs('public.checkout.information')) {
                return $html;
            }

            if (! class_exists(Cart::class)) {
                return $html;
            }

            $cart = Cart::instance('cart');
            $products = $cart->products();

            if ($products->isEmpty()) {
                return $html;
            }

            $currency = get_application_currency()->title;

            $contents = $products->map(function ($product) use ($cart) {
                $cartItem = $cart->content()->first(fn ($item) => $item->id == $product->getKey());
                $quantity = $cartItem ? $cartItem->qty : 1;

                return [
                    'content_id' => (string) ($product->original_product ?? $product)->getKey(),
                    'content_type' => 'product',
                    'content_name' => ($product->original_product ?? $product)->name,
                    'quantity' => $quantity,
                    'price' => (float) ($product->front_sale_price ?? $product->price),
                ];
            })->values()->all();

            $service->bufferClientEvent('InitiateCheckout', [
                'contents' => $contents,
                'value' => (float) $cart->rawSubTotal(),
                'currency' => $currency,
            ]);

            return $html;
        }, 10);
    }

    protected function handleOrderPlaced($event, TikTokPixelService $service): void
    {
        $order = $event->order;
        $order->loadMissing('products');
        $currency = get_application_currency()->title;

        $contents = $order->products->map(fn ($item) => [
            'content_id' => (string) $item->product_id,
            'content_type' => 'product',
            'content_name' => $item->product_name,
            'quantity' => $item->qty,
            'price' => (float) $item->price,
        ])->values()->all();

        $eventId = $service->generateEventId();

        $service->bufferClientEvent('CompletePayment', [
            'contents' => $contents,
            'value' => (float) $order->sub_total,
            'currency' => $currency,
        ], $eventId);

        session()->put('tiktok_pixel_order_event_' . $order->getKey(), $eventId);
    }

    protected function detectSearchEvent(TikTokPixelService $service): void
    {
        add_filter(THEME_FRONT_FOOTER, function (?string $html) use ($service): ?string {
            $searchTerm = request()->query('q') ?? request()->query('keyword');

            if (! $searchTerm || ! request()->routeIs('public.search', 'public.products')) {
                return $html;
            }

            $service->bufferClientEvent('Search', [
                'query' => mb_substr(trim($searchTerm), 0, 500),
            ]);

            return $html;
        }, 10);
    }

    protected function sendServerViewContent($event, TikTokPixelService $service): void
    {
        if (! $service->isEventEnabled('view_content')) {
            return;
        }

        $product = $this->resolveProduct($event->productId);

        if (! $product) {
            return;
        }

        $currency = get_application_currency()->title;
        $eventId = session()->pull('tiktok_pixel_view_content_event_' . $event->productId);
        $userData = $this->buildCustomerUserData($service);

        $service->sendServerEvent('ViewContent', [
            'content_id' => (string) $product->getKey(),
            'content_type' => 'product',
            'content_name' => $product->name,
            'value' => (float) ($product->front_sale_price ?? $product->price),
            'currency' => $currency,
        ], $userData, $eventId);
    }

    protected function sendServerCompletePayment($order, TikTokPixelService $service): void
    {
        if (! $service->isEventEnabled('complete_payment')) {
            return;
        }

        $order->loadMissing('products');
        $currency = get_application_currency()->title;

        $eventId = session()->pull('tiktok_pixel_order_event_' . $order->getKey());

        $contents = $order->products->map(fn ($item) => [
            'content_id' => (string) $item->product_id,
            'content_type' => 'product',
            'content_name' => $item->product_name,
            'quantity' => $item->qty,
            'price' => (float) $item->price,
        ])->values()->all();

        $userData = $this->buildOrderUserData($order, $service);

        $service->sendServerEvent('CompletePayment', [
            'contents' => $contents,
            'value' => (float) $order->sub_total,
            'currency' => $currency,
        ], $userData, $eventId);
    }

    protected function resolveProduct(int|string $productId): mixed
    {
        if (! class_exists(Product::class)) {
            return null;
        }

        if (array_key_exists($productId, $this->resolvedProducts)) {
            return $this->resolvedProducts[$productId];
        }

        $product = Product::query()->find($productId);

        $this->resolvedProducts[$productId] = $product
            ? ($product->original_product ?? $product)
            : null;

        return $this->resolvedProducts[$productId];
    }

    protected function buildCustomerUserData(TikTokPixelService $service): array
    {
        $data = [];

        if ($this->isEcommerceActive() && class_exists(Customer::class)) {
            $customer = auth('customer')->user();

            if ($customer) {
                $data['email'] = $service->hashValue($customer->email);

                if ($customer->phone) {
                    $data['phone'] = $service->hashValue($customer->phone);
                }

                $data['external_id'] = $service->hashValue((string) $customer->getKey());
            }
        }

        return $data;
    }

    protected function buildOrderUserData($order, TikTokPixelService $service): array
    {
        $data = [];

        $address = $order->shippingAddress ?? $order->address;

        if ($address) {
            if ($address->email) {
                $data['email'] = $service->hashValue($address->email);
            }

            if ($address->phone) {
                $data['phone'] = $service->hashValue($address->phone);
            }
        }

        if ($order->user_id) {
            $data['external_id'] = $service->hashValue((string) $order->user_id);
        }

        return $data;
    }
}

@if(count($events) > 0)
<script>
@foreach($events as $event)
  ttq.track(@json($event['event']), @json($event['properties']), { event_id: @json($event['event_id']) });
@endforeach
</script>
@endif

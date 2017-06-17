<tr>
    <td>{{ trans('texts.relation') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.start_date') }}</td>
    <td>{{ trans('texts.duration') }}</td>
    <td>{{ trans('texts.description') }}</td>
</tr>

@foreach ($tasks as $task)
    @if (!$task->relation || !$task->relation->is_deleted)
        <tr>
            <td>{{ $task->present()->relation }}</td>
            @if ($multiUser)
                <td>{{ $task->present()->user }}</td>
            @endif
            <td>{{ $task->getStartTime() }}</td>
            <td>{{ $task->getDuration() }}</td>
            <td>{{ $task->description }}</td>
        </tr>
    @endif
@endforeach

<tr>
    <td></td>
</tr>
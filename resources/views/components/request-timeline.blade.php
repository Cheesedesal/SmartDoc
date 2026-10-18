@props(['request'])

{{-- Port of the reference "timeline" (step.completed / step.current / step.pending), driven by persisted state. --}}
<section class="panel">
    <div class="panel-heading"><div><p class="eyebrow">TIMELINE</p><h2>Processing stages</h2></div></div>
    <ol class="timeline" aria-label="Processing stages">
        @foreach ($request->timeline() as $step)
            <li class="timeline-step is-{{ $step['state'] }}" @if ($step['state'] === 'current') aria-current="step" @endif>
                <strong>{{ $step['stage'] }}</strong>
                <span>
                    @if ($step['state'] === 'upcoming')
                        Waiting
                    @elseif ($step['at'])
                        {{ $step['state'] === 'current' ? 'Since' : 'Done' }} {{ $step['at']->format('M j, Y g:i A') }}
                    @else
                        {{ $step['state'] === 'current' ? 'In progress' : 'Done' }}
                    @endif
                </span>
            </li>
        @endforeach
    </ol>
    @if ($request->status === \App\Enums\RequestStatus::Cancelled)
        <p class="timeline-note">This request was cancelled{{ $request->cancelled_at ? ' on '.$request->cancelled_at->format('M j, Y g:i A') : '' }}.</p>
    @elseif ($request->status === \App\Enums\RequestStatus::Rejected)
        <p class="timeline-note">This request was rejected under the previous system (read-only history).</p>
    @endif
</section>

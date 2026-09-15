@extends('layouts.app', ['title' => 'Edit alert rule'])

@section('content')
    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('alert-rules.update', $rule) }}">
            @csrf
            @method('PUT')
            @include('alert-rules.partials.form', ['rule' => $rule])
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save rule</button>
                <a href="{{ route('alert-rules.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
        @can('delete', $rule)
            <form method="POST" action="{{ route('alert-rules.destroy', $rule) }}" class="mt-3" onsubmit="return confirm('Delete this alert rule?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm" type="submit">Delete rule</button>
            </form>
        @endcan
    </div>
@endsection

@props(['messages'])

@if ($messages)
    @foreach ((array) $messages as $message)
        <p {{ $attributes->merge(['class' => 'text-sm text-red-600 mt-2']) }}>
            {{ $message }}
        </p>
    @endforeach
@endif

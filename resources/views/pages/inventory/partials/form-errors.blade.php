@if ($errors->any())
    <div class="mb-6 rounded-xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
        <p class="font-medium">Periksa kembali isian berikut:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->getMessages() as $key => $messages)
                @unless (str_starts_with($key, 'items.'))
                    <li>{{ $messages[0] }}</li>
                @endunless
            @endforeach
        </ul>
    </div>
@endif

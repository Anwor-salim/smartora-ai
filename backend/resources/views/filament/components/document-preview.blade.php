@php
    $extracted = is_array($document->extracted_data) ? $document->extracted_data : [];
    $totalCount = count($extracted);
    $firstItem = $totalCount > 0 ? (array) array_values($extracted)[0] : [];
    $keys = array_keys($firstItem);
@endphp

<div class="space-y-4 text-sm" dir="rtl">
    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div>
            <span class="text-xs text-gray-500 block">إجمالي السجلات / الأصناف:</span>
            <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ $document->total_items ?? $totalCount }} صنف</span>
        </div>
        <div>
            <span class="text-xs text-gray-500 block">صيغة الملف:</span>
            <span class="font-mono text-gray-700 dark:text-gray-300 uppercase">{{ $document->file_type }}</span>
        </div>
        <div>
            <span class="text-xs text-gray-500 block">الحالة:</span>
            <span class="font-semibold {{ $document->status === 'ready' ? 'text-emerald-500' : 'text-red-500' }}">
                {{ $document->status === 'ready' ? 'جاهز ومعتمد للذكاء الاصطناعي' : 'خطأ بالتحليل' }}
            </span>
        </div>
    </div>

    @if($totalCount > 0)
        <div>
            <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-2">عينة من الأصناف والجداول المستخرجة:</h4>
            <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table class="w-full text-xs text-right divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-800 font-bold">
                        <tr>
                            <th class="p-2">#</th>
                            @foreach(array_slice($keys, 0, 4) as $key)
                                <th class="p-2">{{ $key }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach(array_slice($extracted, 0, 15) as $idx => $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="p-2 font-mono text-gray-400">{{ $idx + 1 }}</td>
                                @foreach(array_slice($keys, 0, 4) as $key)
                                    <td class="p-2 font-medium">{{ is_array($row) ? ($row[$key] ?? '-') : '-' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($totalCount > 15)
                <p class="text-xs text-gray-500 mt-1 italic">* يتم عرض أول 15 صنف كعينة فقط، كامل الملف محفوظ ومتاح للمحرك الذكي.</p>
            @endif
        </div>
    @endif

    <div>
        <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-2">النص الخام المستخرج (Context Snippet):</h4>
        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg font-mono text-xs max-h-48 overflow-y-auto whitespace-pre-wrap border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300">
            {{ Str::limit($document->extracted_text ?? 'لا يوجد نص مستخرج', 1500) }}
        </div>
    </div>
</div>

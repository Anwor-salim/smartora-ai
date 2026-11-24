@php
    $extracted = is_array($document->extracted_data) ? $document->extracted_data : [];
    $isTabularList = count($extracted) > 0 && array_is_list($extracted) && is_array($extracted[0] ?? null);
    $tableRows = $isTabularList ? $extracted : [];
    $totalCount = count($tableRows);
    $firstItem = $totalCount > 0 ? $tableRows[0] : [];
    $keys = array_keys($firstItem);
@endphp

<div class="space-y-4 text-sm" dir="rtl">
    @if($document->status === 'error' && !empty($document->error_message))
        <div class="p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-xs">
            <strong>تنبيه التحليل:</strong> {{ $document->error_message }}
        </div>
    @endif

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
            <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-2">عينة من الأصناف والبيانات المستخرجة:</h4>
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
                        @foreach(array_slice($tableRows, 0, 15) as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="p-2 font-mono text-gray-400">{{ $loop->iteration }}</td>
                                @foreach(array_slice($keys, 0, 4) as $key)
                                    @php
                                        $cellVal = is_array($row) ? ($row[$key] ?? '-') : '-';
                                        if (is_array($cellVal)) {
                                            $cellVal = json_encode($cellVal, JSON_UNESCAPED_UNICODE);
                                        }
                                    @endphp
                                    <td class="p-2 font-medium whitespace-pre-line">{{ $cellVal }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($totalCount > 15)
                <p class="text-xs text-gray-500 mt-1 italic">* يتم عرض أول 15 سجل كعينة فقط، كامل الملف محفوظ ومتاح للمحرك الذكي.</p>
            @endif
        </div>
    @endif

    <div>
        <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-2">النص الخام المستخرج (Context Snippet):</h4>
        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg font-sans text-xs leading-relaxed max-h-64 overflow-y-auto whitespace-pre-wrap border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300" dir="rtl">{{ Str::limit($document->extracted_text ?? 'لا يوجد نص مستخرج', 4000) }}</div>
    </div>
</div>

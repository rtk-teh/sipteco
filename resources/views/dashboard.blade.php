<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Статистика звонков') }}
        </h2>
    </x-slot>

    @php
    $year  = date('Y', strtotime($date));
    $month = date('m', strtotime($date)) - 1; // JS: месяцы начинаются с 0
    $day   = date('d', strtotime($date));
    $chartHeight = (count($operatorStats) * 50);
@endphp

<div class="container mx-auto p-6">
    <h2 class="text-2xl font-bold mb-4">Общий график звонков</h2>

    <!-- Форма выбора даты -->
    <form method="GET" action="{{ route('dashboard') }}" class="mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:space-x-4">
            <label for="date" class="text-lg font-medium">Выберите дату:</label>
            <input type="date" id="date" name="date" value="{{ $date }}" max="{{ now()->format('Y-m-d') }}" class="border border-gray-300 rounded-md p-2">
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md">
                Показать
            </button>
        </div>
    </form>

    <!-- График звонков -->
    <div class="bg-white p-4 rounded-lg shadow-md">
        <div id="dashboard_timeline" style="height: {{ $chartHeight }}px;"></div>
    </div>

    <h2 class="text-2xl font-bold mt-8 mb-4">Общая статистика</h2>
    <div class="bg-white p-4 rounded-lg shadow-md grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 text-gray-700">
        <div><strong>Всего звонков:</strong> {{ $generalStats->total_calls }}</div>
        <div><strong>Не дозвонились:</strong> {{ $generalStats->no_answer_calls }}</div>
        <div><strong>Звонков от 30 сек:</strong> {{ $generalStats->calls_30_sec }}</div>
        <div><strong>Звонков от 120 сек:</strong> {{ $generalStats->calls_120_sec }}</div>
        <div><strong>Общее ожидание:</strong> {{ gmdate('H:i:s', $generalStats->total_wait_time) }}</div>
        <div><strong>Общая продолжительность:</strong> {{ gmdate('H:i:s', $generalStats->total_talk_time) }}</div>
        <div><strong>Средняя продолжительность:</strong> {{ round($generalStats->avg_talk_time, 2) }} сек</div>
    </div>

    <h2 class="text-2xl font-bold mt-8">Графики и статистика по каждому оператору</h2>
    @foreach ($operators as $operator)
        @php
            $totalIncoming = 0;
            $totalOutgoing = 0;

            $totalIncomingCount = 0;
            $totalOutgoingCount = 0;
            $totalMissedCount = 0;

            if (isset($operatorStats[$operator])) {
                foreach ($operatorStats[$operator]['chart'] as $record) {
                    $totalIncoming += $record->total_incoming;
                    $totalOutgoing += $record->total_outgoing;
                    
                    if ($record->total_incoming > 0) {
                        $totalIncomingCount++;
                    }

                    if ($record->total_outgoing > 0) {
                        $totalOutgoingCount++;
                    }

                    if ($record->total_missed_duration > 0) {
                        $totalMissedCount++;
                    }
                }
            }
        @endphp
        <div class="mt-6 bg-white p-4 rounded-lg shadow-md flex flex-row items-center">
            <div class="w-2/3 h-64">
                <h3 class="text-xl font-semibold mb-2">Оператор: {{ $operator }}</h3>
                <div id="chart_operator_{{ $operator }}" class="operator-chart" style="height: 200px;"></div>
            </div>
            <div class="w-1/3 pl-4 text-gray-700">
                <h4 class="text-lg font-semibold mb-2">Статистика</h4>
                @if (isset($operatorStats[$operator]))
                    <p><strong>Звонков:</strong> {{ $operatorStats[$operator]['stats']->total_calls ?? 0 }}</p>
                    <p><strong>Всего входящих:</strong> {{ $totalIncomingCount }}</p>
                    <p><strong>Всего исходящих:</strong> {{ $totalOutgoingCount }}</p>
                    <p><strong>Входящие (продолжительность):</strong> {{ gmdate("H:i:s", $totalIncoming ?? 0) }}</p>
                    <p><strong>Исходящие (продолжительность):</strong> {{ gmdate("H:i:s", $totalOutgoing ?? 0) }}</p>
                    <p><strong>Общая продолжительность:</strong> {{ gmdate("H:i:s", $operatorStats[$operator]['stats']->total_talk_time ?? 0) }}</p>
                    <p><strong>Пропущенные:</strong> {{ $totalMissedCount }}</p>
                @else
                    <p class="text-red-500">Ошибка: Данные не найдены</p>
                @endif
            </div>
        </div>
    @endforeach
</div>
<div id="custom-tooltip" class="absolute bg-gray-800 text-white text-sm p-2 rounded-md shadow-md" style="display: none; position: absolute; z-index: 1000;"></div>
<!-- Подключаем Google Charts -->
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
    google.charts.load("current", { packages: ["timeline"] });
    google.charts.setOnLoadCallback(drawAllCharts);

    function drawAllCharts() {
        console.log("Google Charts загружен, рисуем графики...");
        drawTimeline(); // Рисуем общий график

        @foreach($operators as $operator)
            drawOperatorChart("{{ $operator }}"); // Рисуем график для каждого оператора
        @endforeach
    }


    function drawTimeline() {
        try {
            var container = document.getElementById("dashboard_timeline");
            if (!container) {
                console.error("Ошибка: контейнер dashboard_timeline не найден!");
                return;
            }

            var chart = new google.visualization.Timeline(container);
            var dataTable = new google.visualization.DataTable();

            dataTable.addColumn('string', 'Оператор');
            dataTable.addColumn('string', 'Тип звонка'); // Для цвета
            dataTable.addColumn('date', 'Начало');
            dataTable.addColumn('date', 'Конец');

            var rows = [];

            var incomingColors = ["#28a745", "#a6d854", "#17a2b8"];
            var outgoingColors = ["#007bff", "#ff7300", "#8e44ad"];
            var missedColors = ["#808080"];

            var incomingIndex = 0;
            var outgoingIndex = 0;
            var missedIndex = 0;

            var callColors = {}; // Словарь для хранения цветов звонков

            @foreach($operatorStats as $operator => $records)
                var incomingIndex = 0;
                var outgoingIndex = 0;
                var missedIndex = 0;
                @foreach($records['chart'] as $record)
                    @php
                        if (!isset($record->time)) continue;

                        $parts = explode(':', $record->time);
                        $h = (int)($parts[0] ?? 0);
                        $m = (int)($parts[1] ?? 0);
                        $s = (int)($parts[2] ?? 0);
                        $incoming = (int) $record->total_incoming;
                        $outgoing = (int) $record->total_outgoing;
                        $missed = (int) $record->total_missed_duration;

                    @endphp

                    @if($incoming > 0)
                        var key = "{{ $operator }}_incoming_" + incomingIndex;
                        callColors[key] = incomingColors[incomingIndex];

                        rows.push([
                            "{{ $operator }}",
                            key,
                            new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s }}),
                            new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s + $incoming }})
                        ]);

                        incomingIndex = (incomingIndex + 1) % incomingColors.length;
                    @endif

                    @if($outgoing > 0)
                        var key = "{{ $operator }}_outgoing_" + outgoingIndex;
                        callColors[key] = outgoingColors[outgoingIndex];

                        rows.push([
                            "{{ $operator }}",
                            key,
                            new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s }}),
                            new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s + $outgoing }})
                        ]);

                        outgoingIndex = (outgoingIndex + 1) % outgoingColors.length;
                    @endif

                    @if($missed > 0)
                        var key = "{{ $operator }}_missed_" + missedIndex;
                        callColors[key] = missedColors[missedIndex];

                        rows.push([
                            "{{ $operator }}",
                            key,
                            new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s }}),
                            new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s + $missed }})
                        ]);

                        missedIndex = (missedIndex + 1) % missedColors.length;
                    @endif

                @endforeach
            @endforeach

            if (rows.length > 0) {
                dataTable.addRows(rows);

                var colors = rows.map(row => callColors[row[1]] || "#000000");

                var options = {
                    timeline: { showBarLabels: false },
                    colors: Object.values(callColors),
                    hAxis: {
                        format: 'HH:mm',
                        minValue: new Date({{ $year }}, {{ $month }}, {{ $day }}, 6, 0, 0),
                        maxValue: new Date({{ $year }}, {{ $month }}, {{ $day }}, 19, 0, 0)
                    },                    
                    tooltip: { trigger: 'none' }
                };

                chart.draw(dataTable, options);

                google.visualization.events.addListener(chart, 'onmouseover', function (e) {
                    if (typeof e.row === 'undefined') return;

                    var tooltip = document.getElementById('custom-tooltip');
                    var operator = dataTable.getValue(e.row, 0);
                    var callType = dataTable.getValue(e.row, 1);
                    if (callType.includes("incoming")) {
                        callType = "Входящий";
                    } else if (callType.includes("outgoing")) {
                        callType = "Исходящий";
                    } else if (callType.includes("missed")) {
                        callType = "Пропущенный";
                    } else {
                        callType = "Неизвестный";
                    }

                    var startTime = dataTable.getValue(e.row, 2);
                    var endTime = dataTable.getValue(e.row, 3);

                    // Вычисляем продолжительность в минутах и секундах
                    var durationMs = endTime - startTime;
                    var durationSeconds = Math.floor(durationMs / 1000);
                    var minutes = Math.floor(durationSeconds / 60);
                    var seconds = durationSeconds % 60;
                    var durationFormatted = `${minutes} мин ${seconds} сек`;

                    // Следим за движением мыши
                    document.onmousemove = function (event) {
                        tooltip.style.left = (event.pageX + 10) + 'px';
                        tooltip.style.top = (event.pageY + 10) + 'px';
                    };

                    // Добавляем данные в тултип
                    tooltip.innerHTML = `
                        <strong>Оператор:</strong> ${operator}<br>
                        <strong>Тип:</strong> ${callType}<br>
                        <strong>Начало:</strong> ${startTime.toLocaleTimeString()}<br>
                        <strong>Конец:</strong> ${endTime.toLocaleTimeString()}<br>
                        <strong>Длительность:</strong> ${durationFormatted}
                    `;
                    tooltip.style.display = 'block';
                });

                google.visualization.events.addListener(chart, 'onmouseout', function () {
                    document.getElementById('custom-tooltip').style.display = 'none';
                    document.onmousemove = null;
                });

            } else {
                container.innerHTML = "<p>Нет данных для отображения</p>";
            }
        } catch (error) {
            console.error("Ошибка при отрисовке общего графика:", error);
        }
    }

    function drawOperatorChart(operator) {
        try {
            var container = document.getElementById("chart_operator_" + operator);
            if (!container) {
                console.warn("Контейнер для оператора " + operator + " не найден, пропускаем.");
                return;
            }

            var chart = new google.visualization.Timeline(container);
            var dataTable = new google.visualization.DataTable();

            dataTable.addColumn('string', 'Тип звонка');
            dataTable.addColumn('string', 'Ключ'); // Невидимый столбец
            dataTable.addColumn('date', 'Начало');
            dataTable.addColumn('date', 'Конец');

            var rows = [];

            var incomingColors = ["#28a745", "#a6d854", "#17a2b8"];
            var outgoingColors = ["#007bff", "#ff7300", "#8e44ad"];
            var missedColors = ["#808080"];

            var incomingIndex = 0;
            var outgoingIndex = 0;
            var missedIndex = 0;

            var callColors = {}; // Словарь для хранения цветов звонков

            @foreach($operatorStats as $operator => $records)
                @php

                    usort($records['chart'], function ($a, $b) {
                            $priorityA = ($a->total_incoming > 0) ? 1 : (($a->total_outgoing > 0) ? 2 : 3);
                            $priorityB = ($b->total_incoming > 0) ? 1 : (($b->total_outgoing > 0) ? 2 : 3);
            
                            if ($priorityA !== $priorityB) {
                                return $priorityA - $priorityB;
                            }
            
                            return strcmp($a->time, $b->time);
                    });

                @endphp
                var incomingIndex = 0;
                var outgoingIndex = 0;
                var missedIndex = 0;
                if (operator === "{{ $operator }}") {
                    @foreach($records['chart'] as $record)
                        var key = "";
                        @php
                            if (!isset($record->time)) continue; // Пропускаем записи без time

                            $parts = explode(':', $record->time);
                            $h = (int)($parts[0] ?? 0);
                            $m = (int)($parts[1] ?? 0);
                            $s = (int)($parts[2] ?? 0);
                            $incoming = (int) $record->total_incoming;
                            $outgoing = (int) $record->total_outgoing;
                            $missed = (int) $record->total_missed_duration;

                        @endphp

                        @if($incoming > 0)
                            var key = "{{ $operator }}_incoming_" + incomingIndex;
                            callColors[key] = incomingColors[incomingIndex];

                            rows.push([
                                "Входящий",
                                key,
                                new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s }}),
                                new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s + $incoming }}),
                            ]);

                            incomingIndex = (incomingIndex + 1) % incomingColors.length;

                        @elseif ($outgoing > 0)
                            var key = "{{ $operator }}_outgoing_" + outgoingIndex;
                            callColors[key] = outgoingColors[outgoingIndex];

                            rows.push([
                                "Исходящий",
                                key,
                                new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s }}),
                                new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s + $outgoing }}),
                            ]);

                            outgoingIndex = (outgoingIndex + 1) % outgoingColors.length;

                        @elseif ($missed > 0)
                            var key = "{{ $operator }}_missed_" + missedIndex;
                            callColors[key] = missedColors[missedIndex];
                            
                            rows.push([
                                "Пропущенный",
                                key,
                                new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s }}),
                                new Date({{ $year }}, {{ $month }}, {{ $day }}, {{ $h }}, {{ $m }}, {{ $s + $missed }}),
                            ]);

                            missedIndex = (missedIndex + 1) % missedColors.length;

                        @endif
                    @endforeach
                }
            @endforeach

            if (rows.length > 0) {
                dataTable.addRows(rows);
                var options = {
                    colors: Object.values(callColors),
                    hAxis: {
                        format: 'HH:mm',
                        minValue: new Date({{ $year }}, {{ $month }}, {{ $day }}, 6, 0, 0),
                        maxValue: new Date({{ $year }}, {{ $month }}, {{ $day }}, 19, 0, 0)
                    },
                    timeline: { showBarLabels: false },
                    tooltip: { trigger: 'none' }
                };

                chart.draw(dataTable, options);

                google.visualization.events.addListener(chart, 'onmouseover', function (e) {
                        if (typeof e.row === 'undefined') return;

                        var tooltip = document.getElementById('custom-tooltip');
                        var callType = dataTable.getValue(e.row, 1);
                        if (callType.includes("incoming")) {
                            callType = "Входящий";
                        } else if (callType.includes("outgoing")) {
                            callType = "Исходящий";
                        } else if (callType.includes("missed")) {
                            callType = "Пропущенный";
                        } else {
                            callType = "Неизвестный";
                        }

                        var startTime = dataTable.getValue(e.row, 2);
                        var endTime = dataTable.getValue(e.row, 3);

                        // Вычисляем продолжительность в минутах и секундах
                        var durationMs = endTime - startTime;
                        var durationSeconds = Math.floor(durationMs / 1000);
                        var minutes = Math.floor(durationSeconds / 60);
                        var seconds = durationSeconds % 60;
                        var durationFormatted = `${minutes} мин ${seconds} сек`;

                        // Следим за движением мыши
                        document.onmousemove = function (event) {
                            tooltip.style.left = (event.pageX + 10) + 'px';
                            tooltip.style.top = (event.pageY + 10) + 'px';
                        };

                        // Добавляем данные в тултип
                        tooltip.innerHTML = 
                            `<strong>Тип:</strong> ${callType}<br>
                            <strong>Начало:</strong> ${startTime.toLocaleTimeString()}<br>
                            <strong>Конец:</strong> ${endTime.toLocaleTimeString()}<br>
                            <strong>Длительность:</strong> ${durationFormatted}`
                        ;
                        tooltip.style.display = 'block';
                    });

                    google.visualization.events.addListener(chart, 'onmouseout', function () {
                        document.getElementById('custom-tooltip').style.display = 'none';
                        document.onmousemove = null;
                    });

            } else {
                container.innerHTML = "<p>Нет данных</p>";
            }
        } catch (error) {
            console.error("Ошибка при отрисовке графика оператора " + operator + ":", error);
        }
    }

</script>
</x-app-layout>

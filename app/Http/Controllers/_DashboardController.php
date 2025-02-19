<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $startDate = "$date 06:00:00";
        $endDate = "$date 19:59:59";

        //Получаем уникальных операторов
        $operators = DB::connection('external_db')
            ->table('cdr')
            ->selectRaw("src as operator")
            ->whereBetween('start', [$startDate, $endDate])
            ->whereRaw("CHAR_LENGTH(src) BETWEEN 3 AND 4")
            ->union(
                DB::connection('external_db')
                    ->table('cdr')
                    ->selectRaw("dst as operator")
                    ->whereBetween('start', [$startDate, $endDate])
                    ->whereRaw("CHAR_LENGTH(dst) BETWEEN 3 AND 4")
            )
            ->distinct()
            ->orderBy('operator')
            ->limit(1000)
            ->pluck('operator');

        //Общий график
        $generalChart = DB::connection('external_db')
            ->table('cdr')
            ->selectRaw("
                HOUR(start) as hour,
                COUNT(CASE WHEN CHAR_LENGTH(dst) BETWEEN 3 AND 4 THEN 1 END) as total_incoming,
                COUNT(CASE WHEN CHAR_LENGTH(src) BETWEEN 3 AND 4 THEN 1 END) as total_outgoing
            ")
            ->whereBetween('start', [$startDate, $endDate])
            ->groupBy('hour')
            ->orderBy('hour')
            ->limit(1000)
            ->get();

        //Общая статистика
        $generalStats = DB::connection('external_db')
            ->table('cdr')
            ->selectRaw("
                COUNT(*) as total_calls,
                COUNT(DISTINCT src, start) as total_dials,
                SUM(CASE WHEN answer IS NULL THEN 1 ELSE 0 END) as no_answer_calls,
                SUM(CASE WHEN billsec >= 30 THEN 1 ELSE 0 END) as calls_30_sec,
                SUM(CASE WHEN billsec >= 120 THEN 1 ELSE 0 END) as calls_120_sec,
                SUM(wait) as total_wait_time,
                SUM(billsec) as total_talk_time,
                AVG(billsec) as avg_talk_time,
                SUM(CASE WHEN answer IS NULL AND CHAR_LENGTH(dst) BETWEEN 3 AND 4 THEN 1 ELSE 0 END) as missed_calls
            ")
            ->whereBetween('start', [$startDate, $endDate])
            ->limit(1000)
            ->first();

        //Статистика по каждому оператору
        $operatorStats = [];
        foreach ($operators as $operator) {
            $operatorStats[$operator] = [
                'stats' => DB::connection('external_db')
                    ->table('cdr')
                    ->selectRaw("
                        COUNT(*) as total_calls,
                        COUNT(CASE WHEN billsec > 0 THEN 1 END) as total_dials,
                        SUM(CASE WHEN answer IS NULL THEN 1 ELSE 0 END) as no_answer_calls,
                        SUM(CASE WHEN billsec >= 30 THEN 1 ELSE 0 END) as calls_30_sec,
                        SUM(CASE WHEN billsec >= 120 THEN 1 ELSE 0 END) as calls_120_sec,
                        SUM(wait) as total_wait_time,
                        SUM(billsec) as total_talk_time,
                        AVG(billsec) as avg_talk_time,
                        SUM(CASE WHEN answer IS NULL THEN 1 ELSE 0 END) as missed_calls
                    ")
                    ->whereBetween('start', [$startDate, $endDate])
                    ->where(function ($query) use ($operator) {
                        $query->where('src', $operator)->orWhere('dst', $operator);
                    })
                    ->limit(1000)
                    ->first(),

                'chart' => DB::connection('external_db')
                    ->table('cdr')
                    ->selectRaw("
                        HOUR(start) as hour,
                        COUNT(CASE WHEN dst = ? THEN 1 END) as total_incoming,
                        COUNT(CASE WHEN src = ? THEN 1 END) as total_outgoing
                    ", [$operator, $operator])
                    ->whereBetween('start', [$startDate, $endDate])
                    ->groupBy('hour')
                    ->orderBy('hour')
                    ->limit(1000)
                    ->get()
            ];
        }

        return view('dashboard', compact('date', 'generalChart', 'generalStats', 'operatorStats', 'operators'));
    }

    public function operatorStatistics(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $startDate = "$date 06:00:00";
        $endDate = "$date 19:59:59";
        $selectedOperators = $request->input('operators', []);

        // Получаем всех доступных операторов
        $allOperators = DB::connection('external_db')
            ->table('cdr')
            ->selectRaw("src as operator")
            ->whereRaw("CHAR_LENGTH(src) BETWEEN 3 AND 4")
            ->union(
                DB::connection('external_db')
                    ->table('cdr')
                    ->selectRaw("dst as operator")
                    ->whereRaw("CHAR_LENGTH(dst) BETWEEN 3 AND 4")
            )
            ->distinct()
            ->limit(1000)
            ->orderBy('operator')
            ->pluck('operator');

        $operatorStats = [];

        // Получаем данные только для выбранных операторов
        if (!empty($selectedOperators)) {
            foreach ($selectedOperators as $operator) {
                $operatorStats[$operator] = DB::connection('external_db')
                    ->table('cdr')
                    ->selectRaw("
                        DATE_FORMAT(start, '%H:%i:%s') as time,
                        SUM(CASE WHEN dst = ? THEN billsec ELSE 0 END) as incoming_duration,
                        SUM(CASE WHEN src = ? THEN billsec ELSE 0 END) as outgoing_duration
                    ", [$operator, $operator])
                    ->whereBetween('start', [$startDate, $endDate])
                    ->where(function ($query) use ($operator) {
                        $query->where('src', $operator)
                            ->orWhere('dst', $operator);
                    })
                    ->groupBy('time')
                    ->orderBy('time')
                    ->get()
                    ->toArray();
            }
        }
        // dd($operatorStats);
        return view('operator-statistics', compact('date', 'allOperators', 'selectedOperators', 'operatorStats'));
    }


    // public function _operatorStatistics(Request $request)
    // {
    //     $date = $request->input('date', now()->format('Y-m-d'));
    //     $startDate = "$date 06:00:00";
    //     $endDate = "$date 19:59:59";
    
    //     // Получаем список уникальных операторов
    //     $allOperators = DB::connection('external_db')
    //         ->table('cdr')
    //         ->selectRaw("src as operator")
    //         ->whereBetween('start', [$startDate, $endDate])
    //         ->whereRaw("CHAR_LENGTH(src) BETWEEN 3 AND 4")
    //         ->union(
    //             DB::connection('external_db')
    //                 ->table('cdr')
    //                 ->selectRaw("dst as operator")
    //                 ->whereBetween('start', [$startDate, $endDate])
    //                 ->whereRaw("CHAR_LENGTH(dst) BETWEEN 3 AND 4")
    //         )
    //         ->distinct()
    //         ->orderBy('operator')
    //         ->pluck('operator');
    
    //     // Выбранные операторы (из формы)
    //     $selectedOperators = $request->input('operators', []);
    
    //     // Запрашиваем данные звонков для выбранных операторов
    //     $callLogs = collect();
    //     if (!empty($selectedOperators)) {
    //         $callLogs = DB::connection('external_db')
    //             ->table('cdr')
    //             ->where(function ($query) use ($selectedOperators) {
    //                 $query->whereIn('src', $selectedOperators)
    //                       ->orWhereIn('dst', $selectedOperators);
    //             })
    //             ->whereBetween('start', [$startDate, $endDate])
    //             ->select('start as timestamp', 'src', 'dst', 'billsec')
    //             ->orderBy('start')
    //             ->limit(1000)
    //             ->get()
    //             ->map(function ($call) {
    //                 return [
    //                     'timestamp' => $call->timestamp,
    //                     'duration' => (int) $call->billsec,
    //                     'operator' => in_array($call->dst, range(100, 9999)) ? $call->dst : $call->src,
    //                     'type' => in_array($call->dst, range(100, 9999)) ? "incoming" : "outgoing"
    //                 ];
    //             });
    //     }
    
    //     return view('operator-statistics', compact('date', 'allOperators', 'selectedOperators', 'callLogs'));
    // }    
    
}

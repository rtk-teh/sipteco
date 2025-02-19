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

        // Получаем уникальных операторов
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
            ->pluck('operator')
            ->toArray();

        // Общая статистика
        $generalStats = DB::connection('external_db')
            ->table('cdr')
            ->selectRaw("
                COUNT(*) as total_calls,
                COUNT(DISTINCT src, start) as total_dials,
                SUM(CASE WHEN billsec = 0 THEN 1 ELSE 0 END) as no_answer_calls,
                SUM(CASE WHEN billsec >= 30 THEN 1 ELSE 0 END) as calls_30_sec,
                SUM(CASE WHEN billsec >= 120 THEN 1 ELSE 0 END) as calls_120_sec,
                SUM(wait) as total_wait_time,
                SUM(billsec) as total_talk_time,
                AVG(billsec) as avg_talk_time,
                SUM(CASE WHEN billsec = 0 AND CHAR_LENGTH(dst) BETWEEN 3 AND 4 THEN 1 ELSE 0 END) as missed_calls
            ")
            ->whereBetween('start', [$startDate, $endDate])
            ->limit(1000)
            ->first();

        // Статистика по каждому оператору
        $operatorStats = [];
        foreach ($operators as $operator) {
            $stats = DB::connection('external_db')
                ->table('cdr')
                ->selectRaw("
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN billsec > 0 THEN 1 END) as total_dials,
                    SUM(CASE WHEN billsec = 0 THEN 1 ELSE 0 END) as no_answer_calls,
                    SUM(CASE WHEN billsec >= 30 THEN 1 ELSE 0 END) as calls_30_sec,
                    SUM(CASE WHEN billsec >= 120 THEN 1 ELSE 0 END) as calls_120_sec,
                    SUM(wait) as total_wait_time,
                    SUM(billsec) as total_talk_time,
                    AVG(billsec) as avg_talk_time,
                    SUM(CASE WHEN billsec = 0 AND CHAR_LENGTH(dst) BETWEEN 3 AND 4 THEN 1 ELSE 0 END) as missed_calls
                ")
                ->whereBetween('start', [$startDate, $endDate])
                ->where(function ($query) use ($operator) {
                    $query->where('src', $operator)
                          ->orWhere('dst', $operator);
                })
                ->limit(1000)
                ->first();

            // Данные для графика
            $chartData = DB::connection('external_db')
                ->table('cdr')
                ->selectRaw("
                    DATE_FORMAT(start, '%H:%i:%s') as time,
                    SUM(CASE WHEN dst = ? AND (disposition = 'ANSWERED') AND billsec > 0 THEN billsec ELSE 0 END) as total_incoming,
                    SUM(CASE WHEN src = ? AND disposition = 'ANSWERED' THEN billsec ELSE 0 END) as total_outgoing,
                    SUM(CASE WHEN dst = ? AND (disposition = 'NO ANSWER' OR disposition = 'VOICEMAIL') THEN 1 ELSE 0 END) as total_missed_duration
                ", [$operator, $operator, $operator])
                ->whereBetween('start', [$startDate, $endDate])
                ->where(function ($query) use ($operator) {
                    $query->where('src', $operator)
                        ->orWhere('dst', $operator);
                })
                ->groupByRaw('TIME(start), start')
                ->orderBy('start')
                ->limit(1000)
                ->get()
                ->toArray();

            $operatorStats[$operator] = [
                'stats' => $stats,
                'chart' => $chartData,
            ];
        }

        return view('dashboard', compact('date', 'generalStats', 'operatorStats', 'operators'));
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
                        SUM(CASE WHEN dst = ? AND disposition = 'ANSWERED' AND billsec > 0 THEN billsec ELSE 0 END) as total_incoming,
                        SUM(CASE WHEN src = ? AND disposition = 'ANSWERED' THEN billsec ELSE 0 END) as total_outgoing,
                        SUM(CASE WHEN dst = ? AND disposition = 'NO ANSWER' THEN 1 ELSE 0 END) as total_missed_duration
                    ", [$operator, $operator, $operator])
                    ->whereBetween('start', [$startDate, $endDate])
                    ->where(function ($query) use ($operator) {
                        $query->where('src', $operator)
                            ->orWhere('dst', $operator);
                    })
                    ->groupByRaw('TIME(start), start')
                    ->orderBy('time')
                    ->get()
                    ->toArray();
            }

            $generalStats = DB::connection('external_db')
                ->table('cdr')
                ->selectRaw("
                    COUNT(*) as total_calls,
                    COUNT(DISTINCT src, start) as total_dials,
                    SUM(CASE WHEN billsec = 0 THEN 1 ELSE 0 END) as no_answer_calls,
                    SUM(CASE WHEN billsec > 0 THEN 1 ELSE 0 END) as answer_call,
                    SUM(CASE WHEN billsec >= 30 THEN 1 ELSE 0 END) as calls_30_sec,
                    SUM(CASE WHEN billsec >= 120 THEN 1 ELSE 0 END) as calls_120_sec,
                    SUM(wait) as total_wait_time,
                    SUM(billsec) as total_talk_time,
                    AVG(billsec) as avg_talk_time,
                    SUM(CASE WHEN billsec = 0 AND CHAR_LENGTH(dst) BETWEEN 3 AND 4 THEN 1 ELSE 0 END) as missed_calls
                ")
                ->whereBetween('start', [$startDate, $endDate])
                ->where(function ($query) use ($selectedOperators) {
                    $query->whereIn('src', $selectedOperators)
                        ->orWhereIn('dst', $selectedOperators);
                })
                ->limit(1000)
                ->first();

        } else {
            $generalStats = null;
        }

        return view('operator-statistics', compact('date', 'allOperators', 'selectedOperators', 'operatorStats', 'generalStats'));
    }
}

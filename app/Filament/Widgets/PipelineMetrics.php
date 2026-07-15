<?php

namespace App\Filament\Widgets;

use App\Models\Inquiry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The quote-leak instrument (P0 #7): median response time (received → quote
 * sent) and quote→order conversion, driven by the pipeline timestamps.
 */
class PipelineMetrics extends StatsOverviewWidget
{
    protected static ?int $sort = -5;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            $this->responseTimeStat(),
            $this->conversionStat(),
            $this->quotedStat(),
        ];
    }

    private function responseTimeStat(): Stat
    {
        $minutes = Inquiry::whereNotNull('quote_sent_at')
            ->get(['created_at', 'quote_sent_at'])
            ->map(fn (Inquiry $i) => (int) $i->created_at->diffInMinutes($i->quote_sent_at))
            ->sort()
            ->values();

        if ($minutes->isEmpty()) {
            return Stat::make('Median response time', '—')
                ->description('Received → quote sent')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray');
        }

        $mid = intdiv($minutes->count(), 2);
        $median = $minutes->count() % 2
            ? $minutes[$mid]
            : (int) round(($minutes[$mid - 1] + $minutes[$mid]) / 2);

        return Stat::make('Median response time', $this->humanMinutes($median))
            ->description('Received → quote sent')
            ->descriptionIcon('heroicon-m-clock')
            ->color($median <= 240 ? 'success' : 'warning'); // 4h SLA
    }

    private function conversionStat(): Stat
    {
        $quoted = Inquiry::whereNotNull('quote_sent_at')->count();
        $confirmed = Inquiry::whereNotNull('order_confirmed_at')->count();
        $rate = $quoted > 0 ? round($confirmed / $quoted * 100) : 0;

        return Stat::make('Quote → order conversion', $rate.'%')
            ->description("{$confirmed} confirmed of {$quoted} quoted")
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->color($rate >= 40 ? 'success' : ($quoted > 0 ? 'warning' : 'gray'));
    }

    private function quotedStat(): Stat
    {
        $total = Inquiry::count();
        $quoted = Inquiry::whereNotNull('quote_sent_at')->count();
        $rate = $total > 0 ? round($quoted / $total * 100) : 0;

        return Stat::make('Inquiry → quote rate', $rate.'%')
            ->description("{$quoted} quoted of {$total} inquiries")
            ->descriptionIcon('heroicon-m-document-check')
            ->color('info');
    }

    private function humanMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            $rem = $minutes % 60;

            return $rem ? "{$hours}h {$rem}m" : "{$hours}h";
        }

        $days = intdiv($hours, 24);
        $remH = $hours % 24;

        return $remH ? "{$days}d {$remH}h" : "{$days}d";
    }
}

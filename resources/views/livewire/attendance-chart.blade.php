<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $chartData = [];

    public function mount(array $chartData = []): void
    {
        $this->chartData = $chartData;
    }
}; ?>

<div>
    <div class="h-full">
        <div
            x-data
            x-init="
                new Chart($refs.canvas, {
                    type: 'line',
                    data: {
                        labels: @js(collect($chartData)->pluck('date')),
                        datasets: [{
                            label: 'Attendance',
                            data: @js(collect($chartData)->pluck('attendance')),
                            borderColor: 'rgb(99, 102, 241)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.1,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: value => value + '%'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            "
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>
</div>

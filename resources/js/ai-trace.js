import Chart from 'chart.js/auto';

const formatDate = (value) => {
    if (value.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/) === null) {
        throw new Error(`Unknown date format [${value}].`);
    }

    const [date, time] = value.split(' ');
    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute, second] = time.split(':').map(Number);

    return new Date(Date.UTC(year, month - 1, day, hour, minute, second, 0)).toLocaleString(undefined, {
        year: '2-digit',
        day: '2-digit',
        month: '2-digit',
        hourCycle: 'h24',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        timeZoneName: 'short',
    });
};

window.Chart = Chart;
window.formatDate = formatDate;

const defaultPalette = [
    'rgba(107,114,128,0.5)',
    'rgba(147,51,234,0.5)',
    '#9333ea',
    '#eab308',
    '#e11d48',
    '#14b8a6',
];

const cloneSeries = (series) => {
    try {
        if (typeof structuredClone === 'function') {
            return structuredClone(series ?? {});
        }

        return JSON.parse(JSON.stringify(series ?? {}));
    } catch {
        return { labels: [], datasets: [] };
    }
};

const normalizeSeries = (series) => {
    const labels = Array.isArray(series?.labels) ? series.labels : [];
    const datasets = Array.isArray(series?.datasets) ? series.datasets : [];

    const normalizedDatasets = datasets
        .filter((dataset) => dataset && Array.isArray(dataset.points))
        .map((dataset, index) => {
            const points = Array.from({ length: labels.length }, (_, pointIndex) => Number(dataset.points[pointIndex] ?? 0));

            return {
                key: dataset.key ?? `dataset-${index}`,
                label: dataset.label ?? `Series ${index + 1}`,
                points,
            };
        });

    if (normalizedDatasets.length > 0) {
        return { labels, datasets: normalizedDatasets };
    }

    return {
        labels,
        datasets: [{
            key: 'empty',
            label: 'No data',
            points: Array.from({ length: labels.length }, () => 0),
        }],
    };
};

const chartDatasets = (series, palette, chartType) => series.datasets.map((dataset, index) => {
    const color = palette[index % palette.length];

    if (chartType === 'bar') {
        return {
            key: dataset.key,
            label: dataset.label,
            backgroundColor: color,
            borderColor: color,
            borderWidth: 1,
            borderRadius: 3,
            borderSkipped: false,
            data: dataset.points,
            order: index,
        };
    }

    return {
        key: dataset.key,
        label: dataset.label,
        borderColor: color,
        data: dataset.points,
        order: index,
        fill: false,
    };
});

window.aiTraceDashboard = {
    formatDate,
    multiLineChart(config) {
        let chart;
        let cleanup;
        const palette = Array.isArray(config.palette) && config.palette.length > 0 ? config.palette : defaultPalette;
        const chartType = config.chartType === 'bar' ? 'bar' : 'line';
        const stacked = config.stacked === true;

        const destroyChart = () => {
            if (typeof cleanup === 'function') {
                cleanup();
                cleanup = undefined;
            }

            if (chart) {
                chart.destroy();
                chart = undefined;
            }
        };

        const applySeries = (incomingSeries) => {
            if (! chart) {
                return;
            }

            const nextSeries = normalizeSeries(cloneSeries(incomingSeries));

            chart.data.labels = nextSeries.labels.map(formatDate);
            chart.data.datasets = chartDatasets(nextSeries, palette, chartType);
            chart.update('none');
        };

        return {
            init() {
                const normalized = normalizeSeries(cloneSeries(config.series));

                chart = new Chart(this.$refs.canvas, {
                    type: chartType,
                    data: {
                        labels: normalized.labels.map(formatDate),
                        datasets: chartDatasets(normalized, palette, chartType),
                    },
                    options: {
                        maintainAspectRatio: false,
                        layout: {
                            autoPadding: false,
                            padding: { top: 1 },
                        },
                        datasets: {
                            line: {
                                borderWidth: 2,
                                borderCapStyle: 'round',
                                pointHitRadius: 10,
                                pointStyle: false,
                                tension: 0.2,
                                spanGaps: false,
                                segment: {
                                    borderColor: (ctx) => ctx.p0.raw === 0 && ctx.p1.raw === 0 ? 'transparent' : undefined,
                                },
                            },
                        },
                        scales: {
                            x: { display: false, stacked },
                            y: { display: false, beginAtZero: true, stacked },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                mode: 'index',
                                position: 'nearest',
                                intersect: false,
                                callbacks: {
                                    beforeBody: (context) => context
                                        .map((item) => `${item.dataset.label}: ${item.formattedValue}`)
                                        .join(', '),
                                    label: () => null,
                                },
                            },
                        },
                    },
                });

                cleanup = Livewire.on(config.eventName, ({ series }) => {
                    applySeries(series);
                });

                this.$el.addEventListener('alpine:destroy', () => {
                    destroyChart();
                }, { once: true });
            },
        };
    },
};

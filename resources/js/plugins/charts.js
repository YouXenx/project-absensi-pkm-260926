import { BarController, BarElement, CategoryScale, Chart, Legend, LinearScale, Tooltip } from 'chart.js';

/**
 * Chart.js (tree-shaken to bar charts) for <canvas data-chart> on the admin dashboard.
 * The data comes from a <script type="application/json"> referenced by data-chart-source, rendered by the controller:
 *   { labels: [...], datasets: [{ label, data, color }], stacked?: bool, horizontal?: bool, unit?: string }
 * Colours come from Adminator's CSS variables.
 */

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const numberFormat = new Intl.NumberFormat('id-ID');

function cssVar(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

function buildConfig(spec) {
    const text = cssVar('--t-muted', '#64748b');
    const grid = cssVar('--border-soft', '#e2e8f0');
    const indexAxis = spec.horizontal ? 'y' : 'x';
    const valueAxis = spec.horizontal ? 'x' : 'y';

    return {
        type: 'bar',
        data: {
            labels: spec.labels,
            datasets: spec.datasets.map((dataset) => ({
                label: dataset.label,
                data: dataset.data,
                backgroundColor: cssVar(dataset.color, dataset.color),
                borderRadius: 6,
                maxBarThickness: 28,
            })),
        },
        options: {
            indexAxis,
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400 },
            plugins: {
                legend: { display: spec.datasets.length > 1, position: 'bottom', labels: { color: text, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 3 } },
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.dataset.label}: ${numberFormat.format(context.parsed[valueAxis])}${spec.unit ? ` ${spec.unit}` : ''}`,
                    },
                },
            },
            scales: {
                [indexAxis]: { stacked: Boolean(spec.stacked), ticks: { color: text }, grid: { display: false }, border: { display: false } },
                [valueAxis]: { stacked: Boolean(spec.stacked), beginAtZero: true, ticks: { color: text, precision: 0 }, grid: { color: grid }, border: { display: false } },
            },
        },
    };
}

export function initCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const spec = JSON.parse(document.getElementById(canvas.dataset.chartSource).textContent);

        new Chart(canvas, buildConfig(spec));
    });
}

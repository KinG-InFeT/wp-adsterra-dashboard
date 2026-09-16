(function () {
    'use strict';

    var settings = window.adsterraDashboard;
    var chartData = window.adsterraDashboardChart;
    var canvas = document.getElementById('adsterraStatsCanvas');

    var COLOR_PALETTE = [
        'rgba(102, 126, 234, 1)',
        'rgba(118, 75, 162, 1)',
        'rgba(240, 147, 251, 1)',
        'rgba(245, 87, 108, 1)',
        'rgba(52, 199, 89, 1)',
        'rgba(255, 159, 64, 1)',
        'rgba(54, 162, 235, 1)',
        'rgba(255, 205, 86, 1)'
    ];

    function money(value) {
        return '$ ' + value.toFixed(3).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
    }

    // The data is only printed when the stats were loaded, so there is no canvas on errors
    if (canvas && chartData && window.Chart) {
        new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: chartData.datasets.map(function (dataset, index) {
                    var color = COLOR_PALETTE[index % COLOR_PALETTE.length];
                    return {
                        label: dataset.label,
                        data: dataset.data,
                        backgroundColor: color,
                        borderColor: color,
                        fill: false
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var line = (context.dataset.label || '') + ' : ' + money(context.parsed.y);

                                if (context.datasetIndex !== context.chart.data.datasets.length - 1) {
                                    return line;
                                }

                                var total = 0;
                                context.chart.data.datasets.forEach(function (dataset) {
                                    total += dataset.data[context.dataIndex];
                                });

                                return [line, settings.i18n.total + ' : ' + money(total)];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: chartData.axisTitle
                        }
                    }
                }
            }
        });
    }

    function post(action, data) {
        var body = new FormData();
        body.append('action', action);
        Object.keys(data).forEach(function (key) {
            body.append(key, data[key]);
        });

        return fetch(settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                if (!json.success) {
                    throw new Error('Request failed');
                }
                window.location.reload();
            });
    }

    var monthSelect = document.getElementById('adsterra_dashboard_widget_filter_month');
    if (monthSelect) {
        monthSelect.addEventListener('change', function () {
            post('adsterra_update_month_filter', { nonce: settings.monthNonce, filter_month: monthSelect.value })
                .catch(function () {
                    window.alert(settings.i18n.error);
                });
        });
    }

    var refreshButton = document.getElementById('adsterra_refresh_cache');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            var originalHtml = refreshButton.innerHTML;

            refreshButton.disabled = true;
            refreshButton.innerHTML = '<span class="dashicons dashicons-update spin" style="vertical-align: middle;"></span> ';
            refreshButton.appendChild(document.createTextNode(settings.i18n.refreshing));

            post('adsterra_refresh_cache', { nonce: settings.refreshNonce })
                .catch(function () {
                    refreshButton.disabled = false;
                    refreshButton.innerHTML = originalHtml;
                    window.alert(settings.i18n.error);
                });
        });
    }
})();

let currentChart = null;

function initFinanceChart(pemasukan, pengeluaran, hutang, piutang, currency = 'IDR') {
    const ctx = document.getElementById('financeChart');
    if (!ctx) return;

    const chartType = document.getElementById('chartType').value || 'bar';
    const chartPeriod = document.getElementById('chartPeriod').value || 'total';
    const chartFilter = document.getElementById('chartFilter').value || 'all';

    if (currentChart) {
        currentChart.destroy();
    }

    // Define consistent colors for each type
    const colors = {
        pemasukan: {
            bg: 'rgba(34, 197, 94, 0.2)',
            border: 'rgb(34, 197, 94)',
            bgOpaque: 'rgba(34, 197, 94, 0.6)'
        },
        pengeluaran: {
            bg: 'rgba(239, 68, 68, 0.2)',
            border: 'rgb(239, 68, 68)',
            bgOpaque: 'rgba(239, 68, 68, 0.6)'
        },
        hutang: {
            bg: 'rgba(249, 115, 22, 0.2)',
            border: 'rgb(249, 115, 22)',
            bgOpaque: 'rgba(249, 115, 22, 0.6)'
        },
        piutang: {
            bg: 'rgba(59, 130, 246, 0.2)',
            border: 'rgb(59, 130, 246)',
            bgOpaque: 'rgba(59, 130, 246, 0.6)'
        },
        total: {
            bg: 'rgba(59, 130, 246, 0.2)',
            border: 'rgb(59, 130, 246)',
            bgOpaque: 'rgba(59, 130, 246, 0.6)'
        }
    };

    // Ensure all values are numbers and not NaN
    pemasukan = parseFloat(pemasukan) || 0;
    pengeluaran = parseFloat(pengeluaran) || 0;
    hutang = parseFloat(hutang) || 0;
    piutang = parseFloat(piutang) || 0;

    let datasets = [];
    let labels = [];

    // Always show all data by default
    labels = ['Pemasukan', 'Pengeluaran', 'Hutang', 'Piutang'];
    datasets = [pemasukan, pengeluaran, hutang, piutang];

    if (chartFilter === 'transactions') {
        labels = ['Pemasukan', 'Pengeluaran'];
        datasets = [pemasukan, pengeluaran];
    } else if (chartFilter === 'debts') {
        labels = ['Hutang', 'Piutang'];
        datasets = [hutang, piutang];
    }

    const chartData = {
        labels: labels,
        datasets: [{
            data: datasets,
            backgroundColor: [
                colors.pemasukan.bgOpaque, 
                colors.pengeluaran.bgOpaque,
                colors.hutang.bgOpaque,
                colors.piutang.bgOpaque
            ].slice(0, datasets.length),
            borderColor: [
                colors.pemasukan.border, 
                colors.pengeluaran.border,
                colors.hutang.border,
                colors.piutang.border
            ].slice(0, datasets.length),
            borderWidth: 1
        }]
    };

    const options = {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: window.innerWidth < 768 ? 1 : 2,
        plugins: {
            legend: {
                position: window.innerWidth < 768 ? 'bottom' : 'right',
                labels: {
                    generateLabels: function(chart) {
                        const data = chart.data;
                        if (data.datasets.length) {
                            return data.labels.map((label, i) => ({
                                text: `${label} (${currency} ${new Intl.NumberFormat('id-ID').format(data.datasets[0].data[i])})`,
                                fillStyle: data.datasets[0].backgroundColor[i],
                                hidden: false,
                                lineCap: 'butt',
                                lineDash: [],
                                lineDashOffset: 0,
                                lineJoin: 'miter',
                                lineWidth: 1,
                                strokeStyle: data.datasets[0].borderColor[i],
                                pointStyle: 'circle',
                                rotation: 0
                            }));
                        }
                        return [];
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || '';
                        const value = context.raw;
                        return `${label}: ${currency} ${new Intl.NumberFormat('id-ID').format(value)}`;
                    }
                }
            }
        },
        scales: chartType !== 'pie' ? {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return currency + ' ' + new Intl.NumberFormat('id-ID').format(value);
                    }
                }
            }
        } : undefined
    };

    currentChart = new Chart(ctx, {
        type: chartType,
        data: chartData,
        options: options
    });
}

function formatDate(dateString) {
    const options = { weekday: 'short', day: 'numeric', month: 'short' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
}

function changePeriod(period) {
    localStorage.setItem('preferredPeriod', period);
    if (typeof chartData !== 'undefined') {
        initFinanceChart(chartData.pemasukan, chartData.pengeluaran, chartData.hutang, chartData.piutang, chartData.currency);
    }
}

// Add window resize handler
window.addEventListener('resize', () => {
    if (typeof chartData !== 'undefined') {
        initFinanceChart(chartData.pemasukan, chartData.pengeluaran, chartData.hutang, chartData.piutang, chartData.currency);
    }
});

function changeChartType(type) {
    localStorage.setItem('preferredChartType', type);
    const chartElement = document.getElementById('chartType');
    if (chartElement) {
        chartElement.value = type;
    }
    if (typeof chartData !== 'undefined') {
        initFinanceChart(chartData.pemasukan, chartData.pengeluaran, chartData.hutang, chartData.piutang, chartData.currency);
    }
}

function changeFilter(filter) {
    localStorage.setItem('preferredFilter', filter);
    initFinanceChart(
        chartData.pemasukan,
        chartData.pengeluaran,
        chartData.hutang,
        chartData.piutang,
        chartData.currency
    );
}

function toggleSection(sectionId) {
    const content = document.getElementById(sectionId + '-content');
    const icon = document.getElementById(sectionId + '-icon');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        content.classList.add('hidden');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

// Simpan status collapse ke localStorage
function saveSectionState(sectionId, isHidden) {
    localStorage.setItem('section_' + sectionId, isHidden);
}

// Load status collapse saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    const sections = ['graph', 'storage', 'transactions'];
    sections.forEach(section => {
        const content = document.getElementById(section + '-content');
        const icon = document.getElementById(section + '-icon');
        const isHidden = localStorage.getItem('section_' + section) === 'true';
        
        if (isHidden) {
            content.classList.add('hidden');
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    });

    const financeChart = document.getElementById('financeChart');
    if (financeChart) {
        // Store the values as data attributes
        financeChart.dataset.pemasukan = pemasukan;
        financeChart.dataset.pengeluaran = pengeluaran;
        financeChart.dataset.currency = currency;
        
        // Load preferred period
        const preferredPeriod = localStorage.getItem('preferredPeriod');
        if (preferredPeriod) {
            document.getElementById('chartPeriod').value = preferredPeriod;
        }
        
        // Load preferred chart type from localStorage
        const preferredChartType = localStorage.getItem('preferredChartType');
        if (preferredChartType) {
            document.getElementById('chartType').value = preferredChartType;
        }

        // Load preferred filter
        const preferredFilter = localStorage.getItem('preferredFilter');
        if (preferredFilter) {
            document.getElementById('chartFilter').value = preferredFilter;
        } else {
            document.getElementById('chartFilter').value = 'all';
        }
        
        if (chartData.pemasukan > 0 || chartData.pengeluaran > 0 || chartData.hutang > 0 || chartData.piutang > 0) {
            initFinanceChart(
                chartData.pemasukan,
                chartData.pengeluaran,
                chartData.hutang,
                chartData.piutang,
                chartData.currency
            );
        }
    }
});

function formatNumber(input) {
    // Ambil nilai input dan hilangkan semua titik yang ada
    let value = input.value.replace(/\./g, '');
    
    // Konversi ke number dan format dengan titik
    if (value !== '') {
        const number = parseInt(value);
        if (!isNaN(number)) {
            input.value = number.toLocaleString('id-ID');
            
            // Update hidden input dengan nilai tanpa titik
            const realInput = document.getElementById('real_' + input.name);
            if (realInput) {
                realInput.value = number;
            }
        }
    }
}

// Tambahkan event listener untuk hanya mengizinkan angka
function validateNumberInput(event) {
    // Izinkan: backspace, delete, tab, escape, enter, titik
    if ([46, 8, 9, 27, 13, 190].indexOf(event.keyCode) !== -1 ||
        // Allow: Ctrl+A, Command+A
        (event.keyCode === 65 && (event.ctrlKey === true || event.metaKey === true)) ||
        // Allow: home, end, left, right, down, up
        (event.keyCode >= 35 && event.keyCode <= 40)) {
        return;
    }
    // Hentikan jika bukan angka
    if ((event.shiftKey || (event.keyCode < 48 || event.keyCode > 57)) &&
        (event.keyCode < 96 || event.keyCode > 105)) {
        event.preventDefault();
    }
}

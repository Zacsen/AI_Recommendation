@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-6">

    <h1 class="text-3xl font-bold text-blue-600 mb-6">
        📊 Product Recommendation
    </h1>

    <p class="text-gray-600 mb-4">
        Generate easy-to-understand product recommendations based on real sales behavior,
        demand trends, seasonality, and inventory readiness.
    </p>

    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
        <input id="dashboardSearch"
               type="text"
               placeholder="Search by product name or insight keyword..."
               class="w-full sm:w-2/3 px-4 py-2 border rounded-xl focus:ring focus:ring-blue-200">

        <button id="btnRun"
                class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold transition shadow">
            Compute Recommendations
        </button>
    </div>

    <div id="loading"
         class="mt-4 px-4 py-3 bg-blue-100 border border-blue-300 text-blue-700 rounded-lg hidden">
        🔄 Computing recommendations… Please wait.
    </div>

    <div id="paginationControlsTop" class="flex justify-center items-center mt-6 space-x-2 hidden"></div>

    <div id="resultsContainer"
         class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6 hidden">
    </div>


    <!-- CHARTS -->
    <div id="chartsContainer" class="mt-10 hidden space-y-10">

        <!-- Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Total Sales Bar Chart -->
            <div class="bg-white p-4 rounded-xl shadow">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">
                    📊 Total Sales per Product
                </h3>
                <canvas id="salesChart" class="h-72"></canvas>
            </div>

            <!-- Recommendation Score Line Chart -->
            <div class="bg-white p-4 rounded-xl shadow">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">
                    📈 Recommendation Strength (Final Score)
                </h3>
                <canvas id="trendChart" class="h-72"></canvas>
            </div>

        </div>

        <!-- Row 2 -->
        <div id="additionalCharts" class="grid grid-cols-1 lg:grid-cols-3 gap-6 hidden">

            <!-- Stacked Bar Chart -->
            <div class="lg:col-span-2 bg-white p-4 rounded-xl shadow overflow-x-auto">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">
                    📦 Component Contribution Breakdown
                </h3>
                <div class="min-w-[800px]">
                    <canvas id="stackedBarChart" class="h-72"></canvas>
                </div>
            </div>

            <!-- Pie Chart -->
            <div class="bg-white p-4 rounded-xl shadow flex flex-col items-center">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">
                    🥧 Sales Distribution by Product
                </h3>
                <canvas id="salesPieChart" class="h-64 w-64"></canvas>
            </div>

        </div>
    </div>




    <!-- AI INSIGHTS -->
    <div id="aiInsights" class="mt-10 p-6 border border-gray-200 rounded-xl bg-gray-50 hidden">
        <h2 class="text-2xl font-bold text-purple-600 mb-4">
            🤖 AI Business Insights
        </h2>

        <div id="aiInsightsContent"
             class="text-gray-700 prose prose-sm max-w-none">
            Compute recommendations first to unlock AI insights.
        </div>

        <div id="aiLoading"
             class="mt-3 px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hidden">
            🔄 Generating AI insights…
        </div>
    </div>

    <div id="paginationControlsBottom" class="flex justify-center items-center mt-6 space-x-2 hidden"></div>
</div>

<!-- PAIRS MODAL -->
<div id="pairsModal"
     class="fixed inset-0 flex items-center justify-center z-50 hidden
            backdrop-blur-sm bg-black/20">

    <div class="bg-white rounded-2xl w-11/12 max-w-2xl p-6 shadow-xl border border-gray-200">

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-900">
                Product Pair Insights for
                <span id="modalProductName" class="text-blue-600"></span>
            </h2>
            <button id="closeModal" class="text-gray-500 hover:text-gray-700 text-xl">✖</button>
        </div>

        <input id="modalSearch"
               type="text"
               placeholder="Search paired product..."
               class="w-full px-3 py-2 mb-4 border rounded-lg focus:ring focus:ring-blue-200">

        <div class="max-h-80 overflow-y-auto border rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-100">
                    <tr>
                        <th class="py-2 px-3 text-left">Product</th>
                        <th class="py-2 px-3 text-left">Affinity</th>
                        <th class="py-2 px-3 text-left">Strength</th>
                    </tr>
                </thead>
                <tbody id="modalPairsBody"></tbody>
            </table>
        </div>

        <div id="modalPaginationControls"
             class="flex justify-center items-center mt-4 space-x-2"></div>
    </div>
</div>

<script>
/* ===================== CONFIG ===================== */
const itemsPerPage = 3;
const modalItemsPerPage = 10;

let allResults = [];
let filteredResults = [];
let currentPage = 1;

let currentModalPairs = [];
let modalFilteredPairs = [];
let currentModalPage = 1;

/* ===================== HELPERS ===================== */
const percentage = v => (v * 100).toFixed(1);

function scoreLabel(v) {
    if (v >= 0.85) return "Very Strong";
    if (v >= 0.65) return "Strong";
    if (v >= 0.40) return "Moderate";
    if (v >= 0.20) return "Weak";
    return "Very Weak";
}

function performanceBadge(v) {
    if (v >= 0.7) return "bg-green-100 text-green-700";
    if (v >= 0.4) return "bg-yellow-100 text-yellow-700";
    return "bg-red-100 text-red-700";
}

function scoreColor(v) {
    if (v >= 0.7) return "bg-green-500";
    if (v >= 0.4) return "bg-yellow-500";
    return "bg-red-500";
}

function humanLabel(key) {
    const map = {
        mba: "Likelihood that customers buy this together.",
        content: "Similarity of product attributes (Product Description).",
        collab: "Collaborative similarity based on similar customer behavior.",
        season: "Seasonal performance (weekly, monthly, yearly patterns).",
        trend: "Current trending performance this month.",
        forecast: "Predicted future demand based on past patterns.",
        stock_multiplier: "Boost/penalty based on current stock levels.",
        online_ratio: "Percentage of online sales contribution.",
        otc_ratio: "Percentage of over-the-counter sales contribution.",
        total_sales: "Total sale multiplier. "
    };
    return map[key] || key.replace('_', ' ');
}

function technicalTerm(key) {
    const map = {
        mba: "Market Basket Analysis",
        content: "Content Similarity",
        collab: "Collaborative Filtering",
        season: "Seasonal Trend Analysis",
        trend: "Trend Analysis",
        forecast: "Forecasting",
        stock_multiplier: "Inventory Readiness",
        online_ratio: "Online Sales Ratio",
        otc_ratio: "In-Store Sales Ratio",
        total_sales: "Total Sales Multiplier"
    };
    return map[key] || key.replace('_', ' ');
}


/* ===================== SEARCH ===================== */
document.getElementById('dashboardSearch').addEventListener('input', () => {
    const term = dashboardSearch.value.toLowerCase();
    filteredResults = term
        ? allResults.filter(r => r.name.toLowerCase().includes(term))
        : [...allResults];
    currentPage = 1;
    renderDashboard();
});

/* ===================== DASHBOARD ===================== */
function renderDashboard() {
    const container = document.getElementById('resultsContainer');
    container.innerHTML = '';

    const bestSeller = filteredResults.reduce(
        (a, b) => a.final_score > b.final_score ? a : b,
        filteredResults[0]
    );

    const start = (currentPage - 1) * itemsPerPage;
    const items = filteredResults.slice(start, start + itemsPerPage);

    items.forEach(r => {
        const card = document.createElement('div');
        card.className = "bg-white shadow-xl rounded-3xl p-6 border relative";

        const totalSales = r.components.total_sales || 0;

        card.innerHTML = `
            ${r.id === bestSeller.id ? `
                <div class="absolute top-4 right-4 bg-yellow-400 text-yellow-900 text-xs font-bold px-3 py-1 rounded-full">
                    ⭐ Best Seller
                </div>` : ''}

            <h2 class="text-xl font-bold">${r.name}</h2>

            <p class="mt-1 text-gray-500">
                Overall Recommendation Strength
            </p>

            <p class="text-2xl font-bold text-blue-600">
                ${percentage(r.final_score)}%
            </p>

            <div class="mt-4 space-y-4">
                ${Object.entries(r.components).map(([k,v]) => `
                    <div>
                        <div class="flex justify-between text-sm font-semibold">
                            <span>
                                ${technicalTerm(k)}
                            </span>
                            <span class="px-2 rounded-full text-xs ${performanceBadge(v)}">
                                ${scoreLabel(v)}
                            </span>
                        </div>

                        <div class="w-full bg-gray-200 h-2 rounded-full mt-1">
                            <div class="${scoreColor(v)} h-2 rounded-full"
                                 style="width:${safePercentage(v)}%">
                            </div>
                        </div>

                        <p class="text-xs text-gray-400 mt-1">
                            ${humanLabel(k)}
                        </p>
                    </div>
                `).join('')}
            </div>

            <!-- Total Sales -->
            <div class="mt-4 p-3 bg-gray-50 rounded-lg border">
                <h3 class="text-sm font-bold text-gray-800">Total Sales</h3>
                <p class="text-xs text-gray-500 mt-1">
                    ${totalSales} units sold
                </p>
            </div>

            <button class="openPairsBtn w-full mt-5 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl">
                View Product Pairs
            </button>
        `;

        card.querySelector('.openPairsBtn')
            .addEventListener('click', () => openPairsModal(r.name, r.pairs));

        container.appendChild(card);
    });

    container.classList.remove('hidden');
    renderPagination();
}

function renderPagination() {
    const paginationTop = document.getElementById('paginationControlsTop');
    const paginationBottom = document.getElementById('paginationControlsBottom');

    paginationTop.innerHTML = '';
    paginationBottom.innerHTML = '';

    const totalPages = Math.ceil(filteredResults.length / itemsPerPage);
    if (totalPages <= 1) {
        paginationTop.classList.add('hidden');
        paginationBottom.classList.add('hidden');
        return;
    }

    const createBtn = (label, page) => {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.className = `px-3 py-1 rounded text-xs ${
            page === currentPage
                ? 'bg-blue-600 text-white font-bold'
                : 'bg-gray-200 hover:bg-gray-300'
        }`;
        btn.disabled = page === currentPage;
        btn.onclick = () => {
            currentPage = page;
            renderDashboard();
        };
        return btn;
    };

    // Add Prev
    const prevPage = Math.max(1, currentPage - 1);
    paginationTop.appendChild(createBtn('← Prev', prevPage));
    paginationBottom.appendChild(createBtn('← Prev', prevPage));

    // Add page numbers
    for (let i = 1; i <= totalPages; i++) {
        paginationTop.appendChild(createBtn(i, i));
        paginationBottom.appendChild(createBtn(i, i));
    }

    // Add Next
    const nextPage = Math.min(totalPages, currentPage + 1);
    paginationTop.appendChild(createBtn('Next →', nextPage));
    paginationBottom.appendChild(createBtn('Next →', nextPage));

    paginationTop.classList.remove('hidden');
    paginationBottom.classList.remove('hidden');
}


/* ===================== AI ===================== */
const aiSection = document.getElementById('aiInsights');
const aiContent = document.getElementById('aiInsightsContent');
const aiLoading = document.getElementById('aiLoading');

const aiButton = document.createElement('button');
aiButton.textContent = "Generate AI Insights";
aiButton.className = "mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-xl font-semibold";
aiSection.appendChild(aiButton);

aiButton.addEventListener('click', async () => {
    aiLoading.classList.remove('hidden');
    aiContent.innerHTML = "";

    const res = await fetch("{{ route('recommender.ai') }}", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": "{{ csrf_token() }}",
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ products: allResults })
    });

    const data = await res.json();
    aiLoading.classList.add('hidden');

    aiContent.innerHTML = data.success
        ? `<pre class="whitespace-pre-wrap">${data.insight}</pre>`
        : "<p class='text-red-600'>Failed to generate insights.</p>";
});

/* ===================== MODAL ===================== */

const modal = document.getElementById('pairsModal');
const modalBody = document.getElementById('modalPairsBody');
const modalSearchInput = document.getElementById('modalSearch');
const modalPaginationControls = document.getElementById('modalPaginationControls');
const modalProductName = document.getElementById('modalProductName');

document.getElementById('closeModal').addEventListener('click', () => {
    modal.classList.add('hidden');
    modalSearchInput.value = "";
    currentModalPairs = [];
    modalFilteredPairs = [];
    currentModalPage = 1;
});

function openPairsModal(productName, pairs) {
    modalProductName.textContent = productName;
    currentModalPairs = [...pairs].sort((a, b) => b.score - a.score);
    modalFilteredPairs = [...currentModalPairs];
    currentModalPage = 1;
    renderModalPairs();
    modal.classList.remove('hidden');
}

function renderModalPairs() {
    modalBody.innerHTML = '';

    const start = (currentModalPage - 1) * modalItemsPerPage;
    const items = modalFilteredPairs.slice(start, start + modalItemsPerPage);

    if (items.length === 0) {
        modalBody.innerHTML = `
            <tr>
                <td colspan="3" class="py-4 text-center text-gray-500">
                    No product pairs found.
                </td>
            </tr>
        `;
    } else {
        modalBody.innerHTML = items.map(p => `
            <tr class="border-b hover:bg-gray-50">
                <td class="py-2 px-3">${p.name}</td>
                <td class="py-2 px-3">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${performanceBadge(p.score)}">
                        ${percentage(p.score)}%
                    </span>
                </td>
                <td class="py-2 px-3">
                    ${scoreLabel(p.score)}
                </td>
            </tr>
        `).join('');
    }

    renderModalPagination();
}

function renderModalPagination() {
    modalPaginationControls.innerHTML = '';

    const totalPages = Math.ceil(modalFilteredPairs.length / modalItemsPerPage);
    if (totalPages <= 1) return;

    const createBtn = (label, page) => {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.className = `px-3 py-1 rounded text-xs ${
            page === currentModalPage
                ? 'bg-blue-600 text-white font-bold'
                : 'bg-gray-200 hover:bg-gray-300'
        }`;
        btn.disabled = page === currentModalPage;
        btn.onclick = () => {
            currentModalPage = page;
            renderModalPairs();
        };
        return btn;
    };

    modalPaginationControls.appendChild(
        createBtn('← Prev', Math.max(1, currentModalPage - 1))
    );

    for (let i = 1; i <= totalPages; i++) {
        modalPaginationControls.appendChild(createBtn(i, i));
    }

    modalPaginationControls.appendChild(
        createBtn('Next →', Math.min(totalPages, currentModalPage + 1))
    );
}

modalSearchInput.addEventListener('input', () => {
    const term = modalSearchInput.value.toLowerCase();
    modalFilteredPairs = currentModalPairs.filter(p =>
        p.name.toLowerCase().includes(term)
    );
    currentModalPage = 1;
    renderModalPairs();
});


/* ===================== MAIN ===================== */
document.getElementById('btnRun').addEventListener('click', async () => {
    const loading = document.getElementById('loading');
    const container = document.getElementById('resultsContainer');

    // Clear previous state
    allResults = [];
    filteredResults = [];
    currentPage = 1;

    loading.classList.remove('hidden');
    container.classList.add('hidden');
    container.innerHTML = '';
    document.getElementById('paginationControlsTop').classList.add('hidden');
    document.getElementById('paginationControlsBottom').classList.add('hidden');

    const res = await fetch("{{ route('recommender.run') }}", {
        method:'POST',
        headers:{ 
            'X-CSRF-TOKEN':'{{ csrf_token() }}',
            'Content-Type':'application/json'
        }
    });

    const data = await res.json();
    loading.classList.add('hidden');

    if (!data.success) {
        alert("Failed to compute recommendations.");
        return;
    }

    // Store the fetched data
    allResults = data.results;
    
    // ✅ Set filteredResults before rendering
    filteredResults = [...allResults];

    renderDashboard();
    renderCharts();
    renderAdditionalCharts()
    document.getElementById('chartsContainer').classList.remove('hidden');
    document.getElementById('additionalCharts').classList.remove('hidden');

    aiSection.classList.remove('hidden');
    aiContent.textContent = "Click the button below to generate AI insights based on the latest data.";
});

function safePercentage(value) {
    return Math.min(percentage(value), 100);
}


function renderCharts() {
    const productNames = allResults.map(r => r.name);
    const totalSales = allResults.map(r => r.components.total_sales || 0);
    const finalScores = allResults.map(r => r.final_score * 100); // percentage

    // Destroy previous charts if they exist
    if (window.salesChartInstance) window.salesChartInstance.destroy();
    if (window.trendChartInstance) window.trendChartInstance.destroy();

    // Bar chart for total sales
    const ctxSales = document.getElementById('salesChart').getContext('2d');
    window.salesChartInstance = new Chart(ctxSales, {
        type: 'bar',
        data: {
            labels: productNames,
            datasets: [{
                label: 'Total Sales',
                data: totalSales,
                backgroundColor: 'rgba(59, 130, 246, 0.7)', // Tailwind blue-500
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Total Sales per Product',
                    font: { size: 14, weight: 'bold' }
                },
                legend: { display: false },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Line chart for final score
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    window.trendChartInstance = new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: productNames,
            datasets: [{
                label: 'Recommendation Score (%)',
                data: finalScores,
                backgroundColor: 'rgba(147, 51, 234, 0.2)', // Tailwind purple-600
                borderColor: 'rgba(147, 51, 234, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Final Score per Product',
                    font: { size: 14, weight: 'bold' }
                },
                legend: { display: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}


function renderAdditionalCharts() {
    const productNames = allResults.map(r => r.name);

    // Prepare data for stacked bar
    const componentKeys = ['mba','content','collab','season','trend','forecast','stock_multiplier','online_ratio','otc_ratio'];
    const datasets = componentKeys.map(key => ({
        label: technicalTerm(key),
        data: allResults.map(r => r.components[key] || 0),
        backgroundColor: getComponentColor(key)
    }));

    // Destroy previous charts if exist
    if (window.stackedBarInstance) window.stackedBarInstance.destroy();
    if (window.salesPieInstance) window.salesPieInstance.destroy();

    // Stacked Bar Chart
    const ctxStacked = document.getElementById('stackedBarChart').getContext('2d');
    window.stackedBarInstance = new Chart(ctxStacked, {
        type: 'bar',
        data: { labels: productNames, datasets: datasets },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Component Contribution Breakdown',
                    font: { size: 14, weight: 'bold' }
                }, 
                legend: { position: 'bottom' } },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
            }
        }
    });

    // Pie Chart for total sales
    const totalSales = allResults.map(r => r.components.total_sales || 0);
    const ctxPie = document.getElementById('salesPieChart').getContext('2d');
    window.salesPieInstance = new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: productNames,
            datasets: [{
                data: totalSales,
                backgroundColor: productNames.map(() => randomColor())
            }]
        },
        options: { responsive: true, plugins: { 
            title: {
                    display: true,
                    text: 'Total Sales Distribution',
                    font: { size: 14, weight: 'bold' }
                },
            legend: { position: 'bottom' } } }
    });

    // Show container
    document.getElementById('additionalCharts').classList.remove('hidden');
}

// Helper to generate consistent colors for stacked bar
function getComponentColor(key) {
    const colors = {
        mba: '#3b82f6',
        content: '#f97316',
        collab: '#16a34a',
        season: '#a855f7',
        trend: '#eab308',
        forecast: '#14b8a6',
        stock_multiplier: '#f43f5e',
        online_ratio: '#8b5cf6',
        otc_ratio: '#facc15'
    };
    return colors[key] || '#9ca3af';
}

// Helper for pie chart random colors
function randomColor() {
    const r = Math.floor(Math.random() * 200 + 50);
    const g = Math.floor(Math.random() * 200 + 50);
    const b = Math.floor(Math.random() * 200 + 50);
    return `rgba(${r},${g},${b},0.7)`;
}

</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

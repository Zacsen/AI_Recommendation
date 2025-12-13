@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-6">

    <h1 class="text-3xl font-bold text-blue-600 mb-6">
        📊 Smart Product Recommendation Dashboard
    </h1>

    <p class="text-gray-600 mb-4">
        Click the button below to generate fresh recommendation scores based on sales history,
        item similarity, seasonality, and stock levels.
    </p>

    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
        <input id="dashboardSearch"
               type="text"
               placeholder="Search product recommendations by name or score component..."
               class="w-full sm:w-2/3 px-4 py-2 border rounded-xl focus:ring focus:ring-blue-200">

        <button id="btnRun"
                class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold transition shadow">
            Compute Recommendations
        </button>
    </div>

    <div id="loading"
        class="mt-4 px-4 py-3 bg-blue-100 border border-blue-300 text-blue-700 rounded-lg hidden">
        🔄 Computing recommendations... Please wait.
    </div>

    <div id="paginationControlsTop" class="flex justify-center items-center mt-6 space-x-2 hidden"></div>

    <div id="resultsContainer"
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6 hidden">
    </div>

    <div id="aiInsights" class="mt-10 p-6 border border-gray-200 rounded-xl bg-gray-50 hidden">
        <h2 class="text-2xl font-bold text-blue-600 mb-4">
            🤖 AI Interpretation
        </h2>
        <div id="aiInsightsContent" class="text-gray-700 prose prose-sm max-w-none
            prose-ul:mt-2 prose-ul:mb-2
            prose-li:my-1
            prose-p:my-1">
            AI insights about your product recommendations will appear here.
    </div>
    <div id="aiLoading" class="mt-2 px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hidden">
        🔄 Generating AI insights... Please wait.
    </div>
</div>

    <div id="paginationControlsBottom" class="flex justify-center items-center mt-6 space-x-2 hidden"></div>


</div>

<div id="pairsModal"
     class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">

    <div class="bg-white rounded-2xl w-11/12 max-w-2xl p-6 shadow-xl border border-gray-200">

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-900">
                Product Pair Insights for <span id="modalProductName" class="text-blue-600"></span>
            </h2>
            <button id="closeModal" class="text-gray-500 hover:text-gray-700 text-xl">✖</button>
        </div>

        <input id="modalSearch"
               type="text"
               placeholder="Search pair..."
               class="w-full px-3 py-2 mb-4 border rounded-lg focus:ring focus:ring-blue-200">

        <div class="max-h-80 overflow-y-auto border rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-100 text-gray-700 font-semibold">
                    <tr>
                        <th class="py-2 px-3 border-b text-left">Product</th>
                        <th class="py-2 px-3 border-b text-left">Affinity Score</th>
                        <th class="py-2 px-3 border-b text-left">Strength</th>
                    </tr>
                </thead>
                <tbody id="modalPairsBody"></tbody>
            </table>
        </div>

        <div id="modalPaginationControls" class="flex justify-center items-center mt-4 space-x-2"></div>


        <div class="mt-4 text-xs text-gray-500 border-t pt-3">
            <p>
                <strong>Affinity Score</strong> shows how frequently this product is bought or associated
                together with the selected product, based on purchase patterns and similarity algorithms.
            </p>
        </div>
    </div>
</div>

<script>
/* ---------------------------- CONFIG ---------------------------- */
const itemsPerPage = 6; // dashboard cards per page
const modalItemsPerPage = 10; // modal table rows per page
let allResults = []; // Stores all fetched results
let filteredResults = []; // Stores results filtered by the dashboard search
let currentPage = 1; // Current page for dashboard cards

let currentModalPairs = []; // Stores all pairs for the currently open modal
let currentModalPage = 1; // Current page for the modal pairs table

/* ---------------------------- DASHBOARD SEARCH ---------------------------- */
const dashboardSearchInput = document.getElementById('dashboardSearch');

// Function to filter the results and re-render the dashboard
const filterAndRenderDashboard = () => {
    const term = dashboardSearchInput.value;
    if (!term) {
        filteredResults = [...allResults];
    } else {
        const lowerTerm = term.toLowerCase();
        filteredResults = allResults.filter(r =>
            r.name.toLowerCase().includes(lowerTerm) ||
            Object.keys(r.components).some(key => key.toLowerCase().includes(lowerTerm)) ||
            Object.values(r.components).some(v => v.toString().includes(lowerTerm))
        );
    }
    currentPage = 1; // Reset to first page after search
    renderDashboardCards();
};

dashboardSearchInput.addEventListener('input', filterAndRenderDashboard);


/* ---------------------------- DISPLAY HELPERS ---------------------------- */

function scoreLabel(val) {
    if (val >= 0.85) return "Very Strong";
    if (val >= 0.65) return "Strong";
    if (val >= 0.40) return "Moderate";
    if (val >= 0.20) return "Weak";
    return "Very Weak";
}

function scoreColor(value) {
    if (value >= 0.7) return "bg-green-500";
    if (value >= 0.3) return "bg-yellow-500";
    return "bg-red-500";
}

function percentage(value){
    return (value * 100).toFixed(1);
}

const descriptions = {
    mba: "Market Basket analysis: likelihood that customers buy this together.",
    content: "Similarity of product attributes (category, type, features).",
    collab: "Collaborative similarity based on similar customer behavior.",
    season: "Seasonal performance (weekly, monthly, yearly patterns).",
    trend: "Current trending performance this month.",
    forecast: "Predicted future demand based on past patterns.",
    stock_multiplier: "Boost/penalty based on current stock levels.",
    online_ratio: "Percentage of online sales contribution.",
    otc_ratio: "Percentage of over-the-counter sales contribution."
};


/* ---------------------------- PAGINATION (DASHBOARD) ---------------------------- */

function renderPaginationControls() {
    const totalPages = Math.ceil(filteredResults.length / itemsPerPage);
    const controlsTop = document.getElementById('paginationControlsTop');
    const controlsBottom = document.getElementById('paginationControlsBottom');

    if (totalPages <= 1) {
        controlsTop.innerHTML = '';
        controlsBottom.innerHTML = '';
        controlsTop.classList.add('hidden');
        controlsBottom.classList.add('hidden');
        return;
    }

    controlsTop.classList.remove('hidden');
    controlsBottom.classList.remove('hidden');

    const createButton = (text, page) => {
        const btn = document.createElement('button');
        btn.textContent = text;
        btn.className = `px-3 py-1 rounded-lg transition-colors text-sm ${
            page === currentPage
                ? 'bg-blue-600 text-white font-bold'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
        }`;
        btn.disabled = page === currentPage;
        btn.addEventListener('click', () => {
            currentPage = page;
            renderDashboardCards();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        return btn;
    };

    const generateControls = (container) => {
        container.innerHTML = '';

        // Previous Button
        container.appendChild(createButton('← Prev', currentPage > 1 ? currentPage - 1 : 1));

        // Page Number Buttons (showing a max of 5 pages)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (currentPage - startPage < 2 && endPage < totalPages) endPage = Math.min(totalPages, endPage + (2 - (currentPage - startPage)));
        if (endPage - currentPage < 2 && startPage > 1) startPage = Math.max(1, startPage - (2 - (endPage - currentPage)));

        for (let i = startPage; i <= endPage; i++) {
            container.appendChild(createButton(i, i));
        }

        // Next Button
        container.appendChild(createButton('Next →', currentPage < totalPages ? currentPage + 1 : totalPages));
    };

    generateControls(controlsTop);
    generateControls(controlsBottom);
}


function renderDashboardCards() {
    const container = document.getElementById('resultsContainer');
    container.innerHTML = '';

    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const currentItems = filteredResults.slice(startIndex, endIndex);

    if (currentItems.length === 0 && allResults.length > 0) {
        container.innerHTML = `<p class="text-center text-gray-500 col-span-full">No products matched your search criteria.</p>`;
    } else if (currentItems.length === 0 && allResults.length === 0) {
        // Do nothing if results haven't been computed yet
    }


    currentItems.forEach((r) => {
        const comps = r.components;

        const card = document.createElement('div');
        card.className =
            "bg-white shadow-xl rounded-3xl p-6 border border-gray-200 hover:shadow-2xl transition relative";

        card.innerHTML = `
            <h2 class="text-xl font-bold text-gray-900">${r.name}</h2>

            <p class="mt-1 text-gray-500">
                Final Score:
                <span class="font-bold text-blue-600">${Number(r.final_score).toFixed(4)}</span>
            </p>

            <div class="mt-4 space-y-4">
                ${Object.entries(comps).map(([key, val]) => `
                    <div class="group">
                        <div class="flex justify-between text-sm font-semibold text-gray-700">
                            <span class="flex items-center gap-1">
                                ${key.replace('_', ' ').toUpperCase()}
                                <span class="text-gray-400 text-xs">( ${scoreLabel(val)} )</span>
                            </span>
                            <span>${Number(val).toFixed(4)}</span>
                        </div>

                        <div class="w-full bg-gray-200 rounded-full h-2 mt-1 overflow-hidden">
                            <div class="${scoreColor(val)} h-2 rounded-full transition-all duration-700"
                                style="width: ${percentage(val)}%;">
                            </div>
                        </div>

                        <p class="text-xs text-gray-400 mt-1 hidden group-hover:block">
                            ${descriptions[key] ?? ""}
                        </p>
                    </div>
                `).join('')}
            </div>

            <button class="openPairsBtn w-full mt-5 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl font-medium transition">
                View Product Pairs
            </button>
        `;

        card.querySelector('.openPairsBtn')
            .addEventListener('click', () => openPairsModal(r.name, r.pairs));

        container.appendChild(card);
    });

    renderPaginationControls();
    container.classList.remove('hidden');
}


/* ---------------------------- AI INTEGRATION ---------------------------- */

// After rendering dashboard cards
const container = document.getElementById('resultsContainer');
const aiSection = document.getElementById('aiInsights');
const aiContent = document.getElementById('aiInsightsContent');
const aiLoading = document.getElementById('aiLoading');

aiSection.classList.remove('hidden');
aiContent.textContent = "Click the button below to get AI interpretation.";
const aiButton = document.createElement('button');
aiButton.textContent = "Generate AI Insights";
aiButton.className = "mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-xl font-semibold transition shadow";
aiButton.addEventListener('click', async () => {
    aiLoading.classList.remove('hidden');
    aiContent.innerHTML = ""; // clear old content
    
    try {
        const res = await fetch("{{ route('recommender.ai') }}", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ products: allResults }),
        });

        const data = await res.json();
        aiLoading.classList.add('hidden');

        if (data.success) {
            aiContent.innerHTML = `<pre class="whitespace-pre-wrap text-sm leading-relaxed">${data.insight}</pre>`;        } else {
            aiContent.innerHTML = "<p class='text-red-600'>Failed to generate AI insights.</p>";
        }

    } catch (err) {
        console.error(err);
        aiLoading.classList.add('hidden');
        aiContent.innerHTML = "<p class='text-red-600'>An error occurred while fetching AI insights.</p>";
    }
});

aiSection.appendChild(aiButton);



/* ---------------------------- MODAL CONTROL AND PAGINATION ---------------------------- */

const modal = document.getElementById('pairsModal');
const modalBody = document.getElementById('modalPairsBody');
const modalSearchInput = document.getElementById('modalSearch');
const modalPaginationControls = document.getElementById('modalPaginationControls');
const modalProductName = document.getElementById('modalProductName');
let modalFilteredPairs = []; // Pairs filtered by the modal search box


document.getElementById('closeModal').addEventListener('click', () => {
    modal.classList.add('hidden');
    // Clear state when closing
    modalSearchInput.value = "";
    modalFilteredPairs = [];
    currentModalPairs = [];
    currentModalPage = 1;
});

function strengthLabel(score) {
    if (score >= 0.85) return `<span class="text-green-600 font-semibold">Very Strong</span>`;
    if (score >= 0.65) return `<span class="text-green-500 font-semibold">Strong</span>`;
    if (score >= 0.40) return `<span class="text-yellow-600 font-semibold">Moderate</span>`;
    if (score >= 0.20) return `<span class="text-red-500 font-semibold">Weak</span>`;
    return `<span class="text-red-700 font-semibold">Very Weak</span>`;
}

function badgeColor(score) {
    if (score >= 0.85) return "bg-green-500";
    if (score >= 0.65) return "bg-green-400";
    if (score >= 0.40) return "bg-yellow-500";
    if (score >= 0.20) return "bg-red-400";
    return "bg-red-600";
}


function renderModalPairs() {
    modalBody.innerHTML = '';
    const startIndex = (currentModalPage - 1) * modalItemsPerPage;
    const endIndex = startIndex + modalItemsPerPage;
    const itemsToShow = modalFilteredPairs.slice(startIndex, endIndex);

    if (itemsToShow.length === 0) {
        modalBody.innerHTML = `<tr><td colspan="3" class="py-4 px-3 text-center text-gray-500">No product pairs found.</td></tr>`;
    } else {
        modalBody.innerHTML = itemsToShow.map(p => `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="py-2 px-3">${p.name}</td>

                <td class="py-2 px-3">
                    <span class="px-2 py-1 rounded text-white text-xs ${badgeColor(p.score)}">
                        ${p.score.toFixed(2)}
                    </span>
                </td>

                <td class="py-2 px-3">
                    ${strengthLabel(p.score)}
                </td>
            </tr>
        `).join('');
    }

    renderModalPaginationControls();
}


function renderModalPaginationControls() {
    const totalPages = Math.ceil(modalFilteredPairs.length / modalItemsPerPage);
    modalPaginationControls.innerHTML = '';

    if (totalPages <= 1) return;

    const createButton = (text, page) => {
        const btn = document.createElement('button');
        btn.textContent = text;
        btn.className = `px-3 py-1 rounded-lg transition-colors text-xs ${
            page === currentModalPage
                ? 'bg-blue-600 text-white font-bold'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
        }`;
        btn.disabled = page === currentModalPage;
        btn.addEventListener('click', () => {
            currentModalPage = page;
            renderModalPairs();
        });
        return btn;
    };

    // Previous Button
    modalPaginationControls.appendChild(createButton('← Prev', currentModalPage > 1 ? currentModalPage - 1 : 1));

    // Page Number Buttons (showing a max of 5 pages)
    let startPage = Math.max(1, currentModalPage - 2);
    let endPage = Math.min(totalPages, currentModalPage + 2);

    if (currentModalPage - startPage < 2 && endPage < totalPages) endPage = Math.min(totalPages, endPage + (2 - (currentModalPage - startPage)));
    if (endPage - currentModalPage < 2 && startPage > 1) startPage = Math.max(1, startPage - (2 - (endPage - currentModalPage)));

    for (let i = startPage; i <= endPage; i++) {
        modalPaginationControls.appendChild(createButton(i, i));
    }

    // Next Button
    modalPaginationControls.appendChild(createButton('Next →', currentModalPage < totalPages ? currentModalPage + 1 : totalPages));
}


function filterAndRenderModal() {
    const term = modalSearchInput.value.toLowerCase();
    modalFilteredPairs = currentModalPairs.filter(p => p.name.toLowerCase().includes(term));
    currentModalPage = 1; // Reset to first page after search
    renderModalPairs();
}

modalSearchInput.addEventListener('input', filterAndRenderModal);

function openPairsModal(productName, pairs) {
    modalProductName.textContent = productName;
    currentModalPairs = pairs.sort((a, b) => b.score - a.score); // Sort by score
    modalSearchInput.value = ""; // Clear search when opening
    filterAndRenderModal(); // Initial render and filtering
    modal.classList.remove('hidden');
}


/* ---------------------------- MAIN ACTION ---------------------------- */

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
    

    // Apply dashboard search filter and render the first page
    filterAndRenderDashboard(); 
});

</script>
@endsection
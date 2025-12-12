@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-6">

    <h1 class="text-3xl font-bold text-blue-600 mb-6">
        📊 Recommendation Dashboard
    </h1>

    <button id="btnRun"
        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold transition shadow">
        Compute Recommendations
    </button>

    <div id="loading"
        class="mt-4 px-4 py-3 bg-blue-100 border border-blue-300 text-blue-700 rounded-lg"
        style="display:none;">
        🔄 Computing recommendations... Please wait.
    </div>

    <div id="resultsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6"
        style="display:none;">
    </div>

</div>

<script>
function scoreColor(value) {
    if (value >= 0.7) return "bg-green-500";
    if (value >= 0.3) return "bg-yellow-500";
    return "bg-red-500";
}

function percentage(value){
    return (value * 100).toFixed(1);
}

document.getElementById('btnRun').addEventListener('click', async () => {

    const loading = document.getElementById('loading');
    const container = document.getElementById('resultsContainer');
    loading.style.display = 'block';
    container.style.display = 'none';
    container.innerHTML = '';

    const res = await fetch("{{ route('recommender.run') }}", {
        method:'POST',
        headers:{ 
            'X-CSRF-TOKEN':'{{ csrf_token() }}',
            'Content-Type':'application/json'
        }
    });

    const data = await res.json();
    loading.style.display = 'none';

    if (!data.success) {
        alert("Failed to compute recommendations.");
        return;
    }

    data.results.forEach((r) => {

        const comps = r.components;

        const card = document.createElement('div');
        card.className = "bg-white shadow-xl rounded-3xl p-6 border border-gray-200 hover:shadow-2xl transition relative";

        card.innerHTML = `
            <h2 class="text-xl font-bold text-gray-900">${r.name}</h2>

            <p class="mt-1 text-gray-500">
                Final Score:
                <span class="font-bold text-blue-600">${Number(r.final_score).toFixed(4)}</span>
            </p>

            <div class="mt-4 space-y-4">
                ${Object.entries(comps).map(([key, val]) => `
                    <div>
                        <div class="flex justify-between text-sm font-semibold text-gray-700">
                            <span>${key.replace('_',' ').toUpperCase()}</span>
                            <span>${Number(val).toFixed(4)}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-1 overflow-hidden">
                            <div class="${scoreColor(val)} h-2 rounded-full transition-all duration-700"
                                style="width: ${percentage(val)}%;">
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>

            <button class="viewPairsBtn w-full mt-5 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl font-medium transition">
                View Product Details
            </button>

            <div class="pairsContainer mt-3 hidden">
                ${r.pairs.length > 0 ? r.pairs.map(p => `
                    <div class="flex justify-between text-sm text-gray-700 mb-1">
                        <span>${p.name}</span>
                        <span class="font-semibold">${p.score.toFixed(2)}</span>
                    </div>
                `).join('') : '<p class="text-sm text-gray-400">No paired products</p>'}
            </div>
        `;

        // Toggle pairs
        const btn = card.querySelector('.viewPairsBtn');
        const pairsDiv = card.querySelector('.pairsContainer');
        btn.addEventListener('click', () => {
            pairsDiv.classList.toggle('hidden');
            btn.textContent = pairsDiv.classList.contains('hidden') ? "View Product Details" : "Hide Product Details";
        });

        container.appendChild(card);
    });

    container.style.display = 'grid';
});
</script>
@endsection

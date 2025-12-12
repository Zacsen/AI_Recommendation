<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->name }} - Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-card {
            transition: transform 0.2s;
            border: 1px solid #eee;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    
    <div class="card shadow-sm mb-5">
        <div class="row g-0">
            <div class="col-md-4 bg-white d-flex align-items-center justify-content-center border-end">
                <div class="text-center p-5 text-muted">
                    <h1 class="display-4">📦</h1>
                    <p>Image</p>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card-body p-4">
                    <h5 class="text-uppercase text-muted mb-1">Product Details</h5>
                    <h2 class="fw-bold">{{ $product->name }}</h2>
                    <h3 class="text-primary mb-3">${{ number_format($product->price, 2) }}</h3>
                    
                    <p class="card-text">{{ $product->description ?? 'No description available for this item.' }}</p>
                    
                    <hr>

                    <div class="d-flex align-items-center gap-3">
                        @if($product->current_stock > 10)
                            <span class="badge bg-success px-3 py-2">In Stock ({{ $product->current_stock }})</span>
                        @elseif($product->current_stock > 0)
                            <span class="badge bg-warning text-dark px-3 py-2">Low Stock ({{ $product->current_stock }})</span>
                        @else
                            <span class="badge bg-danger px-3 py-2">Out of Stock</span>
                        @endif
                        
                        <button class="btn btn-lg btn-dark" {{ $product->current_stock <= 0 ? 'disabled' : '' }}>
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <h3 class="fw-bold border-start border-4 border-primary ps-3">Recommended For You</h3>
        <p class="text-muted ps-3 small">Based on trends, similarity, and what others bought.</p>
    </div>

    <div class="row row-cols-1 row-cols-md-3 row-cols-lg-5 g-4">
        @forelse($recommendations as $rec)
            <div class="col">
                <div class="card h-100 product-card bg-white position-relative">
                    
                    @if($rec->current_stock < $rec->low_stock_warning_threshold && $rec->current_stock > 0)
                        <span class="badge bg-warning text-dark stock-badge">Hurry! Low Stock</span>
                    @elseif($rec->bulk_discount > 0)
                        <span class="badge bg-info text-white stock-badge">Bulk Deal</span>
                    @endif

                    <div class="card-body text-center">
                        <div class="mb-3 display-6">🛍️</div> <h6 class="card-title text-truncate" title="{{ $rec->name }}">{{ $rec->name }}</h6>
                        <p class="card-text fw-bold text-primary">${{ number_format($rec->price, 2) }}</p>
                        <p class="small text-muted mb-2">Unit: {{ $rec->unit }}</p>
                    </div>
                    
                    <div class="card-footer bg-white border-top-0">
                        <a href="{{ url('/product/'.$rec->id) }}" class="btn btn-outline-primary w-100 btn-sm">
                            View Item
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-secondary">
                    No specific recommendations available right now. Check out our <a href="#">Best Sellers</a>.
                </div>
            </div>
        @endforelse
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
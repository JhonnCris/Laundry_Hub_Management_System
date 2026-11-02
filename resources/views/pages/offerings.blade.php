@extends('layouts.site')

@section('title', 'Products & Services | SSK Laba Dami')
@section('description', 'Compare laundry services and browse laundry supplies available at SSK Laba Dami in Matina, Davao City.')

@section('content')
    <main class="editorial-page">
        <header class="page-masthead page-width">
            <p class="eyebrow">Products &amp; services <span>·</span> The shop counter</p>
            <h1>What your load<br><em>needs today.</em></h1>
            <p class="masthead-deck">Bring garments for washing, drying and folding. Pick up familiar laundry essentials while you’re here.</p>
            <span class="masthead-mark">01 — 02</span>
        </header>

        <section class="offer-services page-width ruled-section" aria-labelledby="offer-services-title">
            <div class="offer-heading">
                <p class="section-index">01 <span>·</span> Laundry care</p>
                <h2 id="offer-services-title">Choose by the job.</h2>
                <p>Not sure which service fits? Start with what your garments still need.</p>
            </div>
            <ol class="offer-ledger">
                <li><span class="ledger-no">01</span><div><h3>Wash Only</h3><p>For garments that need washing; take them home for drying.</p></div><span class="offer-for">WASH</span></li>
                <li><span class="ledger-no">02</span><div><h3>Dry Only</h3><p>For garments that have already been washed.</p></div><span class="offer-for">DRY</span></li>
                <li><span class="ledger-no">03</span><div><h3>Wash with Dry</h3><p>For a wash and dry in the same visit.</p></div><span class="offer-for">WASH + DRY</span></li>
            </ol>
            <p class="offer-note">Folding is part of the shop’s garment-care work. Ask at the counter about current service rates.</p>
        </section>

        <section class="products-section" aria-labelledby="products-title">
            <div class="page-width products-inner">
                <div class="products-heading">
                    <p class="section-index">02 <span>·</span> In-store supplies</p>
                    <h2 id="products-title">Useful things<br>for the wash.</h2>
                    <p>Current prices and availability from the shop’s inventory.</p>
                </div>
                <div class="product-register">
                    <div class="register-head"><span>ITEM</span><span>PRICE / UNIT</span><span>STATUS</span></div>
                    @forelse ($supplies as $supply)
                        <div class="register-row">
                            <span>{{ $supply->name }}</span>
                            <span>₱{{ number_format((float) $supply->unit_price, 2) }} / {{ $supply->unit }}</span>
                            <span>{{ $supply->quantity_on_hand > 0 ? 'In stock' : 'Check in-store' }}</span>
                        </div>
                    @empty
                        <p class="register-empty">Please ask at the counter for current in-store supplies.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="visit-strip page-width">
            <span class="section-index">03 <span>·</span> Getting here</span>
            <p>Bring your garments or ask about an item at the counter.</p>
            <address>Door 5, Amicitia Complex<br>Amolo Street–Tahimik Avenue<br>Nabua Subdivision, Matina Crossing, Davao City 8000</address>
        </section>
    </main>
@endsection

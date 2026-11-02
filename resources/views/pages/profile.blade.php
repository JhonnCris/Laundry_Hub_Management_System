@extends('layouts.site')

@section('title', 'Company Profile | SSK Laba Dami')
@section('description', 'Meet SSK Laba Dami, opened in 2019 by Abner Trinidad in Matina, Davao City.')

@section('content')
    <main class="editorial-page">
        <header class="page-masthead page-width">
            <p class="eyebrow">Company profile <span>·</span> Matina, Davao City</p>
            <h1>Local work.<br><em>Close to home.</em></h1>
            <p class="masthead-deck">SSK Laba Dami is a neighborhood laundry hub and laundry-supply shop at Amicitia Complex.</p>
            <span class="masthead-mark">EST. 2019</span>
        </header>

        <section class="profile-story page-width ruled-section" aria-labelledby="profile-story-title">
            <p class="section-index">01 <span>·</span> Our story</p>
            <div class="profile-story-copy">
                <h2 id="profile-story-title">One shop, two parts of laundry day.</h2>
                <p>Abner Trinidad opened SSK Laba Dami in 2019 in Matina Crossing, Davao City. The work has two closely linked parts: caring for customers’ garments through washing, drying and folding, and selling laundry supplies customers can pick up in the same visit.</p>
                <p>The shop operates from Door 5 at Amicitia Complex, on Amolo Street–Tahimik Avenue. It is a local service with a practical focus: garment care and the everyday supplies that go with it.</p>
            </div>
            <figure class="profile-photo">
                <img src="{{ asset('images/ssk-laba-dami-shop.jpg') }}" alt="SSK Laba Dami storefront with washing machines visible inside">
                <figcaption><span>THE SHOP</span><strong>AMICITIA COMPLEX · MATINA</strong></figcaption>
            </figure>
        </section>

        <section class="profile-timeline page-width" aria-labelledby="timeline-title">
            <div class="timeline-title">
                <p class="section-index">02 <span>·</span> The work</p>
                <h2 id="timeline-title">A simple line from drop-off to done.</h2>
            </div>
            <ol class="timeline-list">
                <li><time datetime="2019">2019</time><div><h3>The shop opens</h3><p>Abner Trinidad establishes SSK Laba Dami in Matina.</p></div></li>
                <li><span class="timeline-now">TODAY</span><div><h3>Laundry and supplies, together</h3><p>Washing, drying and folding garments, alongside laundry essentials for sale in-store.</p></div></li>
            </ol>
        </section>

        <section class="team-section" aria-labelledby="team-title">
            <div class="page-width team-inner">
                <div class="team-intro">
                    <p class="section-index">03 <span>·</span> The people</p>
                    <h2 id="team-title">The names behind the counter.</h2>
                    <p>No stock portraits or borrowed credentials—just the people identified with the shop.</p>
                </div>
                <dl class="team-list">
                    <div><dt>Abner Trinidad</dt><dd>Owner · founded SSK Laba Dami in 2019</dd></div>
                    <div><dt>Jocelyn Tingson</dt><dd>Employee</dd></div>
                </dl>
            </div>
        </section>

        <section class="page-end page-width" id="visit">
            <div>
                <p>Have a load to bring in?</p>
                <address>Door 5, Amicitia Complex, Amolo Street–Tahimik Avenue,<br>Nabua Subdivision, Matina Crossing, Davao City 8000</address>
            </div>
            <a class="text-link" href="{{ route('offerings') }}">See what the shop offers</a>
        </section>
    </main>
@endsection

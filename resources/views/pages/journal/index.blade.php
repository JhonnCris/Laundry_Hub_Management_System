@extends('layouts.site')

@section('title', 'Journal | SSK Laba Dami')
@section('description', 'The story of SSK Laba Dami, a neighborhood laundry hub opened in 2019 in Matina, Davao City.')

@section('content')
    <main class="editorial-page journal-index">
        <header class="page-masthead page-width">
            <p class="eyebrow">The journal <span>·</span> SSK Laba Dami</p>
            <h1>A note from<br><em>the shop.</em></h1>
            <p class="masthead-deck">The people, place and work behind our neighborhood laundry hub.</p>
        </header>

        <section class="journal-feature page-width" aria-labelledby="journal-story-title">
            <figure class="feature-photo">
                <img src="{{ asset('images/ssk-laba-dami-shop.jpg') }}" alt="SSK Laba Dami storefront with washing machines visible inside">
                <figcaption><span>THE SHOP</span><span>MATINA · EST. 2019</span></figcaption>
            </figure>
            <article class="feature-copy">
                <p class="section-index">Company story <span>·</span> Est. 2019 <span>·</span> 2 min read</p>
                <h2 id="journal-story-title"><a href="{{ route('journal.story') }}">A laundry hub, opened in Matina.</a></h2>
                <p>Owner Abner Trinidad opened SSK Laba Dami in 2019. Today, the shop brings garment care and laundry supplies together at Amicitia Complex in Matina Crossing.</p>
                <a class="text-link" href="{{ route('journal.story') }}">Read the company story</a>
            </article>
            <aside class="feature-aside">
                <span>IN THIS STORY</span>
                <a href="{{ route('company') }}">The shop &amp; its people</a>
                <a href="{{ route('offerings') }}">Services &amp; supplies</a>
            </aside>
        </section>
        <p class="journal-note page-width">This is the shop’s founding story. More journal entries will appear here when they’re ready.</p>
    </main>
@endsection

<?php

/**
 * Typography Examples Template Part
 * Demonstrates the Nalakalu design system
 */
?>

<div class="typography-examples p-8 bg-white">
    <!-- Display Heading -->
    <section class="mb-12">
        <h1 class="font-heading-display text-brown mb-4">
            Display Heading
        </h1>
        <p class="text-brown text-lg">
            Large display text for hero sections and major headings.
        </p>
    </section>

    <!-- Heading 1 -->
    <section class="mb-12">
        <h1 class="font-heading-1 text-brown mb-4">
            Heading 1
        </h1>
        <p class="text-brown text-lg">
            Primary section headings with uppercase styling.
        </p>
    </section>

    <!-- Heading 2 -->
    <section class="mb-12">
        <h2 class="font-heading-2 text-brown mb-4">
            Heading 2
        </h2>
        <p class="text-brown text-lg">
            Secondary headings for subsections.
        </p>
    </section>

    <!-- Color Examples -->
    <section class="mb-12">
        <h2 class="font-heading-2 text-brown mb-6">Color Palette</h2>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-brown text-white p-4 rounded">
                <h3 class="font-heading-2 mb-2">Brown</h3>
                <p class="text-sm">#3D332B</p>
            </div>

            <div class="bg-beige text-brown p-4 rounded">
                <h3 class="font-heading-2 mb-2">Beige</h3>
                <p class="text-sm">#D7C5B4</p>
            </div>

            <div class="bg-black text-white p-4 rounded">
                <h3 class="font-heading-2 mb-2">Black</h3>
                <p class="text-sm">#000000</p>
            </div>

            <div class="bg-white text-brown border-2 border-brown p-4 rounded">
                <h3 class="font-heading-2 mb-2">White</h3>
                <p class="text-sm">#FFFFFF</p>
            </div>
        </div>
    </section>

    <!-- Usage Examples -->
    <section class="mb-12">
        <h2 class="font-heading-2 text-brown mb-6">Usage Examples</h2>

        <div class="bg-gray-100 p-6 rounded">
            <h3 class="text-brown font-bold mb-4">Hero Block Example:</h3>
            <pre class="text-sm text-gray-700"><code>&lt;h1 class="font-heading-1 text-white"&gt;Hero Title&lt;/h1&gt;
&lt;p class="text-xl text-white"&gt;Hero description&lt;/p&gt;</code></pre>
        </div>

        <div class="bg-gray-100 p-6 rounded mt-4">
            <h3 class="text-brown font-bold mb-4">Section Heading Example:</h3>
            <pre class="text-sm text-gray-700"><code>&lt;h2 class="font-heading-2 text-brown"&gt;Section Title&lt;/h2&gt;</code></pre>
        </div>
    </section>
</div>
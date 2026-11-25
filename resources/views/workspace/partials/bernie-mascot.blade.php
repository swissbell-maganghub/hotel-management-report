@props([
    'state' => 'idle',
    'class' => 'w-16 h-20',
])

{{-- Animated Bernie (Swiss-Belhotel Waterfront Mascot) --}}
<svg viewBox="0 0 120 144" fill="none" xmlns="http://www.w3.org/2000/svg"
    data-bernie-state="{{ $state }}"
    class="bernie-svg select-none overflow-visible {{ $class }}"
    aria-hidden="true">
    <!-- Ground Shadow -->
    <ellipse class="bernie-shadow" cx="60" cy="136" rx="30" ry="6" fill="#0F2942" fill-opacity="0.16" />

    <!-- Animated Tail (behind back) -->
    <g class="bernie-tail">
        <path d="M27 98 C12 94, 8 81, 16 74 C21 70, 29 77, 30 89 Z" fill="#7D3B1C" />
        <path d="M13 79 C11 75, 14 72, 18 74 C20 76, 17 81, 13 79 Z" fill="#FFFDF8" />
    </g>

    <!-- Walking / Standing Legs & White Mascot Shoes -->
    <g class="bernie-leg-left">
        <rect x="38" y="102" width="17" height="22" rx="7" fill="#7D3B1C" />
        <rect x="41" y="104" width="6" height="16" rx="3" fill="#944B27" fill-opacity="0.55" />
        <path d="M33 123 C33 118, 56 118, 57 125 L57 130 C57 132, 33 132, 33 128 Z" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.3" />
        <path d="M34 129 H56" stroke="#64748B" stroke-width="1.4" stroke-linecap="round" opacity="0.45" />
    </g>
    <g class="bernie-leg-right">
        <rect x="65" y="102" width="17" height="22" rx="7" fill="#6B3116" />
        <rect x="68" y="104" width="6" height="16" rx="3" fill="#8C4422" fill-opacity="0.55" />
        <path d="M63 123 C63 118, 86 118, 87 125 L87 130 C87 132, 63 132, 63 128 Z" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.3" />
        <path d="M64 129 H86" stroke="#64748B" stroke-width="1.4" stroke-linecap="round" opacity="0.45" />
    </g>

    <!-- Back / Left Arm + White Paw -->
    <g class="bernie-arm-left">
        <path d="M35 66 C23 73, 18 88, 22 98 C25 104, 34 102, 37 94 C40 85, 42 75, 41 68 Z" fill="#FFFDF8" stroke="#E2D8CE" stroke-width="1.2" />
        <!-- Fluffy Glove -->
        <circle cx="24" cy="99" r="7.5" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.2" />
        <circle cx="17.5" cy="97" r="2.2" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="0.9" />
        <circle cx="19" cy="103" r="2.2" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="0.9" />
    </g>

    <!-- Mascot Torso (Brown Fur Vest + Fluffy Cream Belly) -->
    <g class="bernie-torso">
        <ellipse cx="60" cy="89" rx="26" ry="26" fill="#7D3B1C" />
        <ellipse cx="60" cy="91" rx="18" ry="22" fill="#FFFDF8" />
        <!-- Subtle Fur Texture Highlight -->
        <ellipse cx="59" cy="89" rx="12" ry="15" fill="#FFFFFF" fill-opacity="0.7" />
        <!-- Collar Strap -->
        <path d="M40 66 Q60 76 80 66" stroke="#3B1E12" stroke-width="5.2" stroke-linecap="round" fill="none" />
        <path d="M53 69 L60 79 L67 69" stroke="#3B1E12" stroke-width="2.6" stroke-linejoin="round" fill="none" />

        <!-- Iconic Swiss-Belhotel Barrel Collar with Crimson Logo -->
        <g class="bernie-barrel">
            <rect x="46" y="76" width="28" height="17" rx="6.5" fill="#DEB782" stroke="#8F5E2B" stroke-width="1.4" />
            <!-- Barrel Wood Shading & Bands -->
            <path d="M51 76.8 V92.2 M69 76.8 V92.2" stroke="#8F5E2B" stroke-width="1.6" />
            <rect x="53" y="77.5" width="14" height="14" rx="3.2" fill="#FFFDF8" />
            <!-- Swiss-Belhotel Crimson Double-Ribbon Emblem (#E1251B) -->
            <path d="M55.2 79.6 H59.6 C58.3 81.4 58.1 83.2 58.2 84.5 H60 C60 87.3 58.3 89.2 55.2 89.6 Z" fill="#E1251B" />
            <path d="M64.8 89.6 H60.4 C61.7 87.8 61.9 86 61.8 84.5 H60 C60 81.7 61.7 79.8 64.8 79.4 Z" fill="#E1251B" />
            <circle cx="65.7" cy="79.4" r="0.9" fill="#E1251B" />
        </g>
    </g>

    <!-- Front / Waving Right Arm + White Glove -->
    <g class="bernie-arm-right">
        <path d="M84 66 C96 63, 105 51, 104 42 C103 36, 95 38, 91 46 C87 54, 83 60, 81 65 Z" fill="#FFFDF8" stroke="#E2D8CE" stroke-width="1.2" />
        <!-- Waving Fluffy Glove Fingers -->
        <circle cx="101" cy="40" r="7.5" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.2" />
        <circle cx="96" cy="32" r="2.2" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="0.9" />
        <circle cx="101" cy="31" r="2.3" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="0.9" />
        <circle cx="106" cy="33" r="2.2" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="0.9" />
        <circle cx="109" cy="38" r="2.0" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="0.9" />
    </g>

    <!-- Bernie Head & Expressive Face -->
    <g class="bernie-head">
        <!-- Floppy Chestnut Ears -->
        <g class="bernie-ear-left">
            <path d="M35 28 C23 30, 17 48, 22 61 C25 66, 33 64, 36 53 C38 45, 38 35, 35 28 Z" fill="#7D3B1C" stroke="#5A2811" stroke-width="1.1" />
        </g>
        <g class="bernie-ear-right">
            <path d="M85 28 C97 30, 103 48, 98 61 C95 66, 87 64, 84 53 C82 45, 82 35, 85 28 Z" fill="#7D3B1C" stroke="#5A2811" stroke-width="1.1" />
        </g>

        <!-- Head Crown Base -->
        <ellipse cx="60" cy="42" rx="28" ry="26" fill="#FFFDF8" stroke="#E2D8CE" stroke-width="1.1" />

        <!-- Bernie Brown Fur Mask Patches -->
        <path d="M33 39 C33 23, 47 17, 54 20 C52 27, 52 38, 52 44 C46 47, 36 47, 33 39 Z" fill="#7D3B1C" />
        <path d="M87 39 C87 23, 73 17, 66 20 C68 27, 68 38, 68 44 C74 47, 84 47, 87 39 Z" fill="#7D3B1C" />
        <path d="M35 37 C36 27, 45 23, 51 25 C50 31, 49 38, 48 42 C43 44, 37 43, 35 37 Z" fill="#4E2310" />
        <path d="M85 37 C84 27, 75 23, 69 25 C70 31, 71 38, 72 42 C77 44, 83 43, 85 37 Z" fill="#4E2310" />

        <!-- Creamy White Forehead Blaze & Fluffy Muzzle -->
        <path d="M53 18 Q60 15 67 18 L64 43 H56 Z" fill="#FFFDF8" />
        <ellipse cx="60" cy="52" rx="18.5" ry="13.5" fill="#FFFFFF" stroke="#EAE2D6" stroke-width="1" />

        <!-- Expressive Blinking Eyes -->
        <g class="bernie-eye-blink">
            <ellipse cx="45.5" cy="36" rx="4.2" ry="4.7" fill="#18120F" />
            <circle cx="46.8" cy="34.4" r="1.5" fill="#FFFFFF" />
            <circle cx="44.2" cy="37.5" r="0.7" fill="#FFFFFF" fill-opacity="0.8" />

            <ellipse cx="74.5" cy="36" rx="4.2" ry="4.7" fill="#18120F" />
            <circle cx="75.8" cy="34.4" r="1.5" fill="#FFFFFF" />
            <circle cx="73.2" cy="37.5" r="0.7" fill="#FFFFFF" fill-opacity="0.8" />
        </g>

        <!-- Glossy Nose & Happy Mouth / Tongue -->
        <ellipse cx="60" cy="48.5" rx="6.8" ry="4.6" fill="#1E1815" />
        <ellipse cx="58.2" cy="47.2" rx="2.1" ry="1.0" fill="#FFFFFF" fill-opacity="0.35" />
        <path d="M60 53 V56 M51.5 55.5 Q60 62 68.5 55.5" stroke="#2D201A" stroke-width="1.9" stroke-linecap="round" fill="none" />
        <path d="M56.5 58.2 C56.5 62.2, 63.5 62.2, 63.5 58.2 Z" fill="#E1251B" />

        <!-- Subtle Thinking Spark Dots (visible only during think state) -->
        <g class="bernie-think-dots" opacity="0">
            <circle cx="98" cy="18" r="2.2" fill="#1E6B7B" />
            <circle cx="105" cy="11" r="3.1" fill="#F04E30" />
            <circle cx="113" cy="6" r="2.0" fill="#C5A059" />
        </g>
    </g>
</svg>

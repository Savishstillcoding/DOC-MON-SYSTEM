{{--
  Circuit wires with moving pulses, behind the dark panel on every page.
  Include it as the first thing inside <section class="right">:
      @include('partials.wires')
  Styles: public/css/wires.css. Timing: public/js/icon-scroll.js.

  Why it's in the page (not a background image): an SVG background runs on
  its own clock that restarts on every page load. Here the pulses read the
  shared --wires-offset clock, so they keep flowing across pages like the icons.

  One 480x480 tile (a <pattern>), repeated across the panel:
    - 2 "bus" lines run edge to edge, so they join the next tile seamlessly.
    - 18 traces run pad to pad inside the tile.
  Pulses: a dash slides along a wire once per cycle (480 units = tile size,
  so bus pulses flow into the next tile). Each pulse is 5 stacked dashes
  (40/30/20/10/4 long) lined up at the front, so the tail fades out.
  IDs are prefixed "wr-" so they can't clash with anything else on the page.
--}}
@php
    // [wire, duration, start time] - each pulse gets its own speed and start
    // so they don't march in step. Only 9 of the 20 wires have pulses.
    $wirePulses = [
        ['bus-h', '6s',   '-1.3s'],
        ['bus-v', '7.5s', '-4.9s'],
        ['w3',    '4.2s', '-0.4s'],
        ['w5',    '3.6s', '-2.2s'],
        ['w7',    '4.7s', '-1.9s'],
        ['w8',    '5.9s', '-0.8s'],
        ['w13',   '5.4s', '-2.9s'],
        ['w16',   '3.3s', '-1.6s'],
        ['w18',   '3.8s', '-3.1s'],
    ];
@endphp
<div class="wires" aria-hidden="true">
  <svg width="100%" height="100%" fill="none" stroke-linecap="round">
    <defs>
      {{-- bus lines: edge to edge, exactly 480 long --}}
      <path id="wr-bus-h" d="M0 200H480"/>
      <path id="wr-bus-v" d="M340 480V0"/>
      {{-- traces: pad to pad (drawn in the direction the pulse travels) --}}
      <path id="wr-w3"  d="M40 60H140V120H220"/>
      <path id="wr-w4"  d="M300 160V120H260V40"/>
      <path id="wr-w5"  d="M380 40V100H440V140"/>
      <path id="wr-w6"  d="M160 360H60V260"/>
      <path id="wr-w7"  d="M180 280H280V380H240"/>
      <path id="wr-w8"  d="M400 400H440V260H380"/>
      <path id="wr-w9"  d="M180 400V440H40"/>
      <path id="wr-w10" d="M260 440H320V400"/>
      <path id="wr-w11" d="M100 200V240H40"/>
      <path id="wr-w12" d="M340 320H310V350"/>
      <path id="wr-w13" d="M60 150H120V180H200"/>
      <path id="wr-w14" d="M460 180H390"/>
      <path id="wr-w15" d="M200 230H300V260"/>
      <path id="wr-w16" d="M90 290V330H140"/>
      <path id="wr-w17" d="M370 450H450V420"/>
      <path id="wr-w18" d="M215 350V420H245"/>
      <path id="wr-w19" d="M320 170V30"/>
      <path id="wr-w20" d="M30 25H110"/>

      <pattern id="wr-tile" width="480" height="480" patternUnits="userSpaceOnUse">
        {{-- the dim wires --}}
        <g class="wire">
          <use href="#wr-bus-h"/><use href="#wr-bus-v"/>
          @foreach (['w3','w4','w5','w6','w7','w8','w9','w10','w11','w12','w13','w14','w15','w16','w17','w18','w19','w20'] as $wire)
            <use href="#wr-{{ $wire }}"/>
          @endforeach
        </g>

        {{-- round pads at trace ends, branch points and the bus crossing --}}
        <circle class="pad" cx="340" cy="200" r="3.5"/>
        @foreach ([[40,60],[220,120],[300,160],[260,40],[380,40],[440,140],[160,360],[60,260],[180,280],[240,380],[400,400],[380,260],[180,400],[40,440],[260,440],[320,400],[100,200],[40,240],[340,320],[310,350],[60,150],[200,180],[460,180],[390,180],[200,230],[300,260],[90,290],[140,330],[370,450],[450,420],[215,350],[245,420],[320,170],[320,30],[30,25],[110,25]] as [$cx, $cy])
          <circle class="pad" cx="{{ $cx }}" cy="{{ $cy }}" r="3"/>
        @endforeach

        {{-- pulses: 5 stacked dashes (t1..t5) per wire make the fading tail --}}
        @foreach ($wirePulses as [$wire, $dur, $seg])
          <g class="pulse" style="--dur: {{ $dur }}; --seg: {{ $seg }}">
            @for ($t = 1; $t <= 5; $t++)
              <use href="#wr-{{ $wire }}" class="t{{ $t }}"/>
            @endfor
          </g>
        @endforeach
      </pattern>
    </defs>

    <rect width="100%" height="100%" fill="url(#wr-tile)"/>
  </svg>
</div>

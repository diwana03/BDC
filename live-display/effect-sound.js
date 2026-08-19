(() => {
  'use strict';
  const soundRequested = new URLSearchParams(location.search).get('sound') === '1';
  let audio = null, master = null, output = null, enabled = soundRequested, lastClass = '', pendingEffect = '', audioGate = null;

  const hideAudioGate = () => {
    if (!audioGate) return;
    audioGate.remove();
    audioGate = null;
  };

  const unlock = async () => {
    if (!enabled) return;
    if (!audio) {
      audio = new (window.AudioContext || window.webkitAudioContext)();
      master = audio.createDynamicsCompressor();
      master.threshold.value = -16; master.knee.value = 12; master.ratio.value = 5;
      master.attack.value = .004; master.release.value = .24;
      output = audio.createGain();
      output.gain.value = 1.35;
      master.connect(output).connect(audio.destination);
    }
    try { await audio.resume(); } catch (_) {}
    if (audio?.state === 'running') hideAudioGate();
    if (audio?.state === 'running' && pendingEffect) {
      const effect = pendingEffect;
      pendingEffect = '';
      play(effect);
    }
  };

  const showAudioGate = () => {
    if (!soundRequested || audioGate || audio?.state === 'running') return;
    audioGate = document.createElement('button');
    audioGate.id = 'projectorAudioGate';
    audioGate.type = 'button';
    audioGate.setAttribute('aria-label', 'Start projector and activate effect sound');
    audioGate.innerHTML = '<strong>START PROJECTOR</strong><span>Click once to activate effect sound</span>';
    Object.assign(audioGate.style, {
      position: 'fixed', inset: '0', width: '100%', height: '100%', zIndex: '2147483647',
      border: '0', cursor: 'pointer', color: '#fff', background: 'radial-gradient(circle at center, #6e1834 0%, #210d1b 46%, #080d18 100%)',
      display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '1rem',
      fontFamily: 'inherit', letterSpacing: '.06em'
    });
    audioGate.querySelector('strong').style.fontSize = 'clamp(2rem, 6vw, 5.5rem)';
    audioGate.querySelector('span').style.fontSize = 'clamp(1rem, 2vw, 1.65rem)';
    audioGate.addEventListener('click', unlock);
    document.body.appendChild(audioGate);
  };
  if (soundRequested) {
    showAudioGate();
    unlock();
    addEventListener('pointerdown', unlock, { passive: true });
    addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') unlock();
    });
  }

  const ready = () => enabled && audio && master && audio.state === 'running';
  const tone = (frequency, duration, type = 'sine', gain = .08, delay = 0, endFrequency = frequency) => {
    if (!ready()) return;
    const oscillator = audio.createOscillator(), volume = audio.createGain(), start = audio.currentTime + delay;
    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, start);
    oscillator.frequency.exponentialRampToValueAtTime(Math.max(20, endFrequency), start + duration);
    volume.gain.setValueAtTime(.0001, start);
    volume.gain.exponentialRampToValueAtTime(gain, start + Math.min(.018, duration / 4));
    volume.gain.exponentialRampToValueAtTime(.0001, start + duration);
    oscillator.connect(volume).connect(master);
    oscillator.start(start); oscillator.stop(start + duration + .03);
  };
  const noise = (duration, gain = .1, delay = 0, highpass = 120, lowpass = 12000) => {
    if (!ready()) return;
    const length = Math.ceil(audio.sampleRate * duration), buffer = audio.createBuffer(1, length, audio.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < length; i++) data[i] = Math.random() * 2 - 1;
    const source = audio.createBufferSource(), hp = audio.createBiquadFilter(), lp = audio.createBiquadFilter(), volume = audio.createGain();
    const start = audio.currentTime + delay;
    hp.type = 'highpass'; hp.frequency.value = highpass; lp.type = 'lowpass'; lp.frequency.value = lowpass;
    volume.gain.setValueAtTime(gain, start); volume.gain.exponentialRampToValueAtTime(.0001, start + duration);
    source.buffer = buffer; source.connect(hp).connect(lp).connect(volume).connect(master); source.start(start);
  };
  const impact = (delay = 0, strength = 1) => {
    tone(105, .52, 'sine', .16 * strength, delay, 42);
    tone(62, .7, 'sine', .13 * strength, delay + .015, 30);
    noise(.24, .12 * strength, delay, 45, 2600);
  };
  const sparkle = (delay = 0, gain = .045) => {
    [1047, 1319, 1568, 2093].forEach((f, i) => tone(f, .34, 'sine', gain, delay + i * .075, f * 1.04));
  };
  const firework = (delay, pitch = 1) => {
    tone(190 * pitch, .55, 'sine', .055, delay, 720 * pitch);
    impact(delay + .58, .72); noise(.75, .105, delay + .58, 650, 11000);
    for (let i = 0; i < 9; i++) tone((780 + Math.random() * 1500) * pitch, .18 + Math.random() * .25, 'sine', .018, delay + .62 + Math.random() * .48);
  };
  const play = (effect) => {
    if (!ready()) { pendingEffect = effect; unlock(); return; }
    if (effect === 'countdown') {
      [0, 1, 2, 3, 4].forEach((delay, i) => { impact(delay, .48 + i * .07); tone(440 + i * 70, .18, 'triangle', .07, delay); });
      impact(4.88, 1.15); sparkle(4.9, .052);
    } else if (effect === 'drumroll') {
      for (let i = 0; i < 40; i++) { const delay = i * .12; noise(.055, .025 + i * .0012, delay, 900, 6500); tone(i % 2 ? 150 : 118, .07, 'triangle', .026 + i * .0008, delay, 82); }
      impact(4.86, 1.05);
    } else if (effect === 'fireworks') {
      [0, .72, 1.42, 2.24, 3.05, 4.02].forEach((delay, i) => firework(delay, .86 + (i % 3) * .12));
    } else if (effect === 'confetti') {
      impact(0, .72); [523, 659, 784].forEach((f, i) => tone(f, 1.1, 'triangle', .055, i * .045)); sparkle(.22, .05);
    } else if (effect === 'gold_rain') {
      impact(0, .82); [392, 494, 587, 784].forEach((f, i) => tone(f, 1.45, 'sine', .05, i * .09)); sparkle(.32, .055);
    } else if (effect === 'laser_sweep') {
      tone(1450, .72, 'sawtooth', .045, 0, 170); tone(320, .58, 'square', .03, .28, 1780); noise(.32, .045, .42, 1800, 12000);
    } else if (effect === 'champion_impact') {
      impact(0, 1.25); [262, 330, 392, 523].forEach((f, i) => tone(f, 1.75, i < 2 ? 'triangle' : 'sine', .065, .1 + i * .055));
      sparkle(.34, .06); firework(.72, 1.08); firework(1.45, .92);
    }
  };
  const detectEffect = () => {
    const current = document.getElementById('fx')?.className || '';
    if (current === lastClass) return;
    lastClass = current;
    const effect = ['champion_impact', 'laser_sweep', 'gold_rain', 'fireworks', 'confetti', 'drumroll', 'countdown'].find(name => current.includes(name));
    if (effect) play(effect);
  };
  new MutationObserver(detectEffect).observe(document.getElementById('fx'), { attributes: true, attributeFilter: ['class'] });
  detectEffect();
})();

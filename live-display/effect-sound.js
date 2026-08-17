(() => {
  'use strict';
  const soundRequested = new URLSearchParams(location.search).get('sound') === '1';
  let audio = null;
  let enabled = soundRequested;
  let lastClass = '';

  const unlock = async () => {
    if (!enabled) return;
    if (!audio) audio = new (window.AudioContext || window.webkitAudioContext)();
    try { await audio.resume(); } catch (_) {}
  };
  if (soundRequested) {
    unlock();
    addEventListener('pointerdown', unlock, { once: true, passive: true });
    addEventListener('keydown', unlock, { once: true });
  }

  const tone = (frequency, duration, type = 'sine', gain = 0.08, delay = 0) => {
    if (!enabled || !audio || audio.state !== 'running') return;
    const oscillator = audio.createOscillator();
    const volume = audio.createGain();
    const start = audio.currentTime + delay;
    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, start);
    volume.gain.setValueAtTime(0.0001, start);
    volume.gain.exponentialRampToValueAtTime(gain, start + 0.02);
    volume.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    oscillator.connect(volume).connect(audio.destination);
    oscillator.start(start);
    oscillator.stop(start + duration + 0.03);
  };
  const play = (effect) => {
    if (effect === 'countdown') [440, 480, 520, 580, 700].forEach((frequency, i) => tone(frequency, .16, 'square', .045, i));
    else if (effect === 'drumroll') for (let i = 0; i < 180; i++) tone(72 + Math.min(90, i * .5), .11, 'triangle', .032, i * .16);
    else if (effect === 'fireworks') for (let i = 0; i < 6; i++) tone(95 + Math.random() * 180, .48, 'sawtooth', .04, i * .34);
    else if (effect === 'confetti' || effect === 'gold_rain') [523, 659, 784, 1047].forEach((f, i) => tone(f, .38, 'sine', .045, i * .1));
    else if (effect === 'laser_sweep') [880, 660, 440, 990].forEach((f, i) => tone(f, .22, 'sawtooth', .035, i * .11));
    else if (effect === 'champion_impact') { tone(64, .8, 'sine', .12); tone(523, .9, 'triangle', .06, .08); }
  };
  new MutationObserver(() => {
    const current = document.getElementById('fx')?.className || '';
    if (current === lastClass) return;
    lastClass = current;
    const effect = ['champion_impact', 'laser_sweep', 'gold_rain', 'fireworks', 'confetti', 'drumroll', 'countdown'].find(name => current.includes(name));
    if (effect) play(effect);
  }).observe(document.getElementById('fx'), { attributes: true, attributeFilter: ['class'] });
})();

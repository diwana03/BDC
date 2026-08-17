(() => {
  'use strict';
  let audio = null;
  let enabled = false;
  let lastClass = '';
  const button = document.createElement('button');
  button.type = 'button';
  button.textContent = '🔇 Enable Sound';
  Object.assign(button.style, {
    position: 'fixed', right: '14px', bottom: '14px', zIndex: '2147483647',
    padding: '10px 14px', borderRadius: '8px', border: '1px solid #fff',
    background: 'rgba(17,24,39,.88)', color: '#fff', fontWeight: '700', cursor: 'pointer'
  });
  document.body.appendChild(button);

  const tone = (frequency, duration, type = 'sine', gain = 0.08, delay = 0) => {
    if (!enabled || !audio) return;
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
    if (effect === 'drumroll') for (let i = 0; i < 18; i++) tone(72 + i * 2, .12, 'triangle', .055, i * .12);
    else if (effect === 'fireworks') for (let i = 0; i < 6; i++) tone(95 + Math.random() * 180, .48, 'sawtooth', .04, i * .34);
    else if (effect === 'confetti' || effect === 'gold_rain') [523, 659, 784, 1047].forEach((f, i) => tone(f, .38, 'sine', .045, i * .1));
    else if (effect === 'laser_sweep') [880, 660, 440, 990].forEach((f, i) => tone(f, .22, 'sawtooth', .035, i * .11));
    else if (effect === 'champion_impact') { tone(64, .8, 'sine', .12); tone(523, .9, 'triangle', .06, .08); }
  };
  button.addEventListener('click', async () => {
    if (!audio) audio = new (window.AudioContext || window.webkitAudioContext)();
    await audio.resume();
    enabled = !enabled;
    button.textContent = enabled ? '🔊 Sound On' : '🔇 Sound Off';
  });
  new MutationObserver(() => {
    const current = document.getElementById('fx')?.className || '';
    if (current === lastClass) return;
    lastClass = current;
    const effect = ['champion_impact', 'laser_sweep', 'gold_rain', 'fireworks', 'confetti', 'drumroll'].find(name => current.includes(name));
    if (effect) play(effect);
  }).observe(document.getElementById('fx'), { attributes: true, attributeFilter: ['class'] });
})();

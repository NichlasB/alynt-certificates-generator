// Frontend JavaScript entry point.
import './style.css';

document.addEventListener('DOMContentLoaded', () => {
  const target = document.querySelector('.acg-certificate-form [data-acg-focus-target="true"]');
  if (!(target instanceof HTMLElement)) {
    return;
  }

  target.focus({ preventScroll: true });
  target.scrollIntoView({ block: 'center', behavior: 'smooth' });
});

import { state, dom, updatePreviewRefs } from './state.js';
import { renderOverlay } from './overlay.js';

export const setPreviewImage = (url, width, height) => {
  const previewContainer = dom.builder.querySelector('.acg-template-preview');
  if (!previewContainer) {
    return;
  }

  if (width && height) {
    state.authoritativeWidth = width;
    state.authoritativeHeight = height;
    dom.builder.dataset.imageWidth = width;
    dom.builder.dataset.imageHeight = height;
  }

  previewContainer.innerHTML = `
    <img id="acg-template-image" src="${url}" alt="" />
    <div id="acg-template-overlay" class="acg-template-overlay"></div>
  `;

  updatePreviewRefs();

  if (dom.previewImage) {
    if (!dom.previewImage.complete) {
      dom.previewImage.addEventListener('load', () => {
        if (!state.authoritativeWidth || !state.authoritativeHeight) {
          state.authoritativeWidth = dom.previewImage.naturalWidth;
          state.authoritativeHeight = dom.previewImage.naturalHeight;
          dom.builder.dataset.imageWidth = state.authoritativeWidth;
          dom.builder.dataset.imageHeight = state.authoritativeHeight;
        }
        renderOverlay();
      });
    } else {
      if (!state.authoritativeWidth || !state.authoritativeHeight) {
        state.authoritativeWidth = dom.previewImage.naturalWidth;
        state.authoritativeHeight = dom.previewImage.naturalHeight;
        dom.builder.dataset.imageWidth = state.authoritativeWidth;
        dom.builder.dataset.imageHeight = state.authoritativeHeight;
      }
      renderOverlay();
    }
  }
};

export const initMediaPicker = () => {
  if (!dom.imageButton || !dom.imageIdInput || !dom.imagePreview) {
    return;
  }

  dom.imageButton.addEventListener('click', () => {
    const frame = window.wp?.media({
      title: 'Select certificate template',
      button: { text: 'Use this image' },
      multiple: false,
    });

    if (!frame) {
      return;
    }

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      dom.imageIdInput.value = attachment.id;
      dom.imagePreview.innerHTML = `<img src="${attachment.url}" style="max-width:100%;height:auto;" alt="" />`;
      setPreviewImage(attachment.url, attachment.width, attachment.height);
    });

    frame.open();
  });
};

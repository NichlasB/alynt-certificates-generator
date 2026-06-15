import { state, dom, updatePreviewRefs } from './state.js';
import { renderOverlay } from './overlay.js';
import { i18n } from './i18n.js';

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

  previewContainer.replaceChildren();
  const image = document.createElement('img');
  image.id = 'acg-template-image';
  image.src = url;
  image.alt = '';

  const overlay = document.createElement('div');
  overlay.id = 'acg-template-overlay';
  overlay.className = 'acg-template-overlay';

  previewContainer.append(image, overlay);

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
      title: i18n.selectCertificateTemplate,
      button: { text: i18n.useThisImage },
      multiple: false,
    });

    if (!frame) {
      return;
    }

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      dom.imageIdInput.value = attachment.id;
      dom.imagePreview.replaceChildren();
      const image = document.createElement('img');
      image.src = attachment.url;
      image.alt = '';
      image.style.maxWidth = '100%';
      image.style.height = 'auto';
      dom.imagePreview.appendChild(image);
      setPreviewImage(attachment.url, attachment.width, attachment.height);
    });

    frame.open();
  });
};

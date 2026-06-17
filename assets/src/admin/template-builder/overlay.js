import { state, dom } from './state.js';
import { getScale, getAlign, getAnchorOffsetPx, clamp01 } from './coordinates.js';
import { enableDrag, enableKeyboardMove, updateOverlayPosition, updateRowPositionInputs } from './drag.js';

export const applyMarkerStyles = (marker, variable) => {
  const { width, height, imgWidth, imgHeight } = getScale();
  const style = variable.style || {};

  const displayScale = imgWidth > 0 && imgHeight > 0
    ? Math.min(width / imgWidth, height / imgHeight)
    : 1;

  marker.style.fontFamily = style.font_family || 'Arial';
  marker.style.fontSize = `${Math.round((style.font_size || 24) * displayScale)}px`;
  marker.style.color = style.color || '#000000';
  marker.style.fontWeight = style.bold ? 'bold' : 'normal';
  marker.style.fontStyle = style.italic ? 'italic' : 'normal';
  marker.style.textAlign = style.align || 'left';
  const lineHeight = Number(style.line_height);
  marker.style.lineHeight = String(Number.isFinite(lineHeight) ? Math.min(3, Math.max(0.8, lineHeight)) : 1.2);

  const maxWidth = Number(style.text_max_width) || 0;
  if (maxWidth > 0) {
    const displayWidth = Math.round(Math.min(maxWidth, imgWidth || maxWidth) * displayScale);
    marker.style.width = `${displayWidth}px`;
    marker.style.maxWidth = `${displayWidth}px`;
    marker.style.whiteSpace = 'normal';
  } else {
    marker.style.width = 'auto';
    marker.style.maxWidth = 'none';
    marker.style.whiteSpace = 'nowrap';
  }
};

export const renderOverlay = () => {
  if (!dom.overlay || !dom.previewImage) {
    return;
  }
  dom.overlay.innerHTML = '';
  state.variables.forEach((variable) => {
    const marker = document.createElement('div');
    marker.className = 'acg-variable-marker';
    marker.dataset.varId = variable.id;
    marker.textContent = variable.label || variable.key;

    applyMarkerStyles(marker, variable);

    if (variable.display_on_certificate === false) {
      marker.style.opacity = '0.4';
      marker.style.textDecoration = 'line-through';
    }

    dom.overlay.appendChild(marker);

    if (variable.coord_mode === 'percent_left') {
      const align = getAlign(variable);
      if (align === 'center' || align === 'right') {
        const { imgWidth } = getScale();
        const markerWidth = marker.offsetWidth || 0;
        const anchorOffsetImgPx = imgWidth > 0
          ? (getAnchorOffsetPx(markerWidth, align) / (dom.previewImage.clientWidth || 1)) * imgWidth
          : 0;
        const xImgPx = variable.x * imgWidth;
        const migrated = (xImgPx + anchorOffsetImgPx) / imgWidth;
        variable.x = clamp01(migrated);
        variable.coord_mode = 'percent_anchor';
        state.migratedOnLoad = true;
        updateRowPositionInputs(variable);
      }
    }

    updateOverlayPosition(marker, variable);
    enableDrag(marker, variable);
    enableKeyboardMove(marker, variable);
  });
};

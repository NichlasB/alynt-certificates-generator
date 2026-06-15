import { state, dom } from './state.js';
import { getScale, getAlign, getAnchorOffsetPx, clamp01 } from './coordinates.js';
import { setSaveStatus, updateHiddenInput } from './rest-save.js';
import { i18n, sprintfString } from './i18n.js';

export const updateRowPositionInputs = (variable) => {
  const row = dom.tableBody.querySelector(`tr[data-var-id="${variable.id}"]`);
  if (!row) {
    return;
  }
  const xInput = row.querySelector('input[data-field="x"]');
  const yInput = row.querySelector('input[data-field="y"]');
  const formatPercentage = (value) => `${(value * 100).toFixed(1)}%`;
  if (xInput) {
    xInput.value = formatPercentage(variable.x);
  }
  if (yInput) {
    yInput.value = formatPercentage(variable.y);
  }
};

export const updateOverlayPosition = (marker, variable) => {
  if (!dom.previewImage) {
    return;
  }
  const { width, height } = getScale();
  const align = getAlign(variable);
  const markerWidth = marker.offsetWidth || 0;

  const anchorX = variable.x * width;
  const left = variable.coord_mode === 'percent_anchor'
    ? anchorX - getAnchorOffsetPx(markerWidth, align)
    : variable.x * width;

  marker.style.left = `${Math.round(left)}px`;
  marker.style.top = `${Math.round(variable.y * height)}px`;
};

const updateMarkerLabel = (marker, variable) => {
  const variableLabel = variable.label || variable.key || i18n.variable;
  marker.setAttribute('aria-label', sprintfString(i18n.moveMarkerInstructions, variableLabel));
};

const announceMarkerPosition = (variable) => {
  setSaveStatus(
    sprintfString(
      i18n.markerPositionUpdated,
      `${(variable.x * 100).toFixed(1)}%, ${(variable.y * 100).toFixed(1)}%`,
    ),
  );
};

export const enableKeyboardMove = (marker, variable) => {
  marker.tabIndex = 0;
  marker.setAttribute('role', 'button');
  updateMarkerLabel(marker, variable);

  marker.addEventListener('keydown', (event) => {
    const keyDeltas = {
      ArrowLeft: [-1, 0],
      ArrowRight: [1, 0],
      ArrowUp: [0, -1],
      ArrowDown: [0, 1],
    };
    const delta = keyDeltas[event.key];
    if (!delta) {
      return;
    }

    event.preventDefault();
    const { width, height } = getScale();
    const stepPx = event.shiftKey ? 10 : 1;
    const deltaX = width > 0 ? (delta[0] * stepPx) / width : 0;
    const deltaY = height > 0 ? (delta[1] * stepPx) / height : 0;

    variable.x = clamp01(variable.x + deltaX);
    variable.y = clamp01(variable.y + deltaY);
    variable.coord_mode = 'percent_anchor';

    updateOverlayPosition(marker, variable);
    updateRowPositionInputs(variable);
    updateHiddenInput();
    announceMarkerPosition(variable);
  });
};

export const enableDrag = (marker, variable) => {
  let dragging = false;
  let startX = 0;
  let startY = 0;
  let originLeft = 0;
  let originTop = 0;

  marker.addEventListener('pointerdown', (event) => {
    event.preventDefault();
    dragging = true;
    startX = event.clientX;
    startY = event.clientY;
    originLeft = marker.offsetLeft;
    originTop = marker.offsetTop;
    marker.setPointerCapture(event.pointerId);
  });

  marker.addEventListener('pointermove', (event) => {
    if (!dragging) {
      return;
    }
    const { width, height } = getScale();
    const align = getAlign(variable);
    const markerWidth = marker.offsetWidth || 0;
    const anchorOffset = getAnchorOffsetPx(markerWidth, align);
    const deltaX = event.clientX - startX;
    const deltaY = event.clientY - startY;
    let nextLeft = originLeft + deltaX;
    let nextTop = originTop + deltaY;
    const nextAnchorX = Math.max(0, Math.min(width, nextLeft + anchorOffset));
    nextLeft = nextAnchorX - anchorOffset;
    nextTop = Math.max(0, Math.min(height, nextTop));
    marker.style.left = `${Math.round(nextLeft)}px`;
    marker.style.top = `${Math.round(nextTop)}px`;
  });

  marker.addEventListener('pointerup', (event) => {
    if (!dragging) {
      return;
    }
    dragging = false;
    const { width, height } = getScale();
    const align = getAlign(variable);
    const markerWidth = marker.offsetWidth || 0;
    const anchorOffset = getAnchorOffsetPx(markerWidth, align);
    const left = marker.offsetLeft;
    const top = marker.offsetTop;

    const anchorX = left + anchorOffset;
    variable.x = width > 0 ? anchorX / width : 0;
    variable.y = height > 0 ? top / height : 0;

    variable.x = clamp01(variable.x);
    variable.y = clamp01(variable.y);
    variable.coord_mode = 'percent_anchor';

    updateRowPositionInputs(variable);
    updateHiddenInput();
    marker.releasePointerCapture(event.pointerId);
    announceMarkerPosition(variable);
  });
};

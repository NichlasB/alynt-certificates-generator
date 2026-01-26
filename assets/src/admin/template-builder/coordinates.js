import { state, dom } from './state.js';

export const clamp01 = (value) => Math.max(0, Math.min(1, value));

export const isLegacyPixelCoordinate = (value) => value > 1;

export const getAlign = (variable) => variable?.style?.align || 'left';

export const getAnchorOffsetPx = (markerWidth, align) => {
  if (align === 'center') {
    return markerWidth / 2;
  }
  if (align === 'right') {
    return markerWidth;
  }
  return 0;
};

export const formatPercentage = (value) => `${(value * 100).toFixed(1)}%`;

export const parsePercentageInput = (input) => {
  const str = String(input).trim();
  if (str.endsWith('%')) {
    return parseFloat(str) / 100;
  }
  const num = parseFloat(str);
  if (num >= 0 && num <= 1) {
    return num;
  }
  if (num > 1 && num <= 100) {
    return num / 100;
  }
  return 0;
};

export const getScale = () => {
  if (!dom.previewImage) {
    return {
      scaleX: 1,
      scaleY: 1,
      width: 0,
      height: 0,
      imgWidth: state.authoritativeWidth,
      imgHeight: state.authoritativeHeight,
    };
  }

  const displayWidth = dom.previewImage.clientWidth || 1;
  const displayHeight = dom.previewImage.clientHeight || 1;

  const imgWidth = state.authoritativeWidth || dom.previewImage.naturalWidth || 1;
  const imgHeight = state.authoritativeHeight || dom.previewImage.naturalHeight || 1;

  return {
    scaleX: displayWidth / imgWidth,
    scaleY: displayHeight / imgHeight,
    width: displayWidth,
    height: displayHeight,
    imgWidth,
    imgHeight,
  };
};

export const migrateCoordinates = (variable) => {
  const x = Number.isFinite(variable.x) ? variable.x : 0;
  const y = Number.isFinite(variable.y) ? variable.y : 0;

  const coordMode = variable.coord_mode || '';

  if (coordMode === 'percent_anchor') {
    return { ...variable, x, y, coord_mode: 'percent_anchor' };
  }

  if (isLegacyPixelCoordinate(x) || isLegacyPixelCoordinate(y)) {
    state.migratedOnLoad = true;

    let newX = x;
    let newY = y;

    if (state.authoritativeWidth > 0 && isLegacyPixelCoordinate(x)) {
      newX = x / state.authoritativeWidth;
    }
    if (state.authoritativeHeight > 0 && isLegacyPixelCoordinate(y)) {
      newY = y / state.authoritativeHeight;
    }

    return { ...variable, x: clamp01(newX), y: clamp01(newY), coord_mode: 'percent_left' };
  }

  return { ...variable, x: clamp01(x), y: clamp01(y), coord_mode: coordMode || 'percent_left' };
};

import { state } from './state.js';
import { dateFormats, autoTypes, alignOptions } from './constants.js';
import { updateHiddenInput } from './rest-save.js';
import { buildSelect, addFontsToGroup } from './ui-helpers.js';
import { i18n, sprintfCount } from './i18n.js';

export const createStyleCell = (variable, renderOverlay) => {
  const styleCell = document.createElement('td');
  styleCell.className = 'acg-variable-style';

  const fontSelect = document.createElement('select');
  fontSelect.setAttribute('aria-label', i18n.font);

  const systemFontList = state.allFonts.filter((f) => f.type === 'system' || !f.type);
  const globalFontList = state.allFonts.filter((f) => f.type === 'global');
  const templateFontList = state.allFonts.filter((f) => f.type === 'template');

  addFontsToGroup(fontSelect, systemFontList, i18n.systemFonts, variable.style.font_family);
  addFontsToGroup(fontSelect, globalFontList, i18n.customFontsGlobal, variable.style.font_family);
  addFontsToGroup(fontSelect, templateFontList, i18n.customFontsTemplate, variable.style.font_family);

  fontSelect.addEventListener('change', () => {
    variable.style.font_family = fontSelect.value;
    updateHiddenInput();
    renderOverlay();
  });

  const sizeInput = document.createElement('input');
  sizeInput.type = 'number';
  sizeInput.setAttribute('aria-label', i18n.size);
  sizeInput.min = '8';
  sizeInput.value = variable.style.font_size;
  sizeInput.addEventListener('input', () => {
    variable.style.font_size = Number(sizeInput.value) || 0;
    updateHiddenInput();
    renderOverlay();
  });

  const colorInput = document.createElement('input');
  colorInput.type = 'color';
  colorInput.setAttribute('aria-label', i18n.color);
  colorInput.value = variable.style.color;
  colorInput.addEventListener('input', () => {
    variable.style.color = colorInput.value;
    updateHiddenInput();
    renderOverlay();
  });

  const alignSelect = buildSelect(alignOptions, variable.style.align || 'left');
  alignSelect.setAttribute('aria-label', i18n.align);
  alignSelect.addEventListener('change', () => {
    variable.style.align = alignSelect.value;
    updateHiddenInput();
    renderOverlay();
  });

  const boldInput = document.createElement('input');
  boldInput.type = 'checkbox';
  boldInput.setAttribute('aria-label', i18n.bold);
  boldInput.checked = Boolean(variable.style.bold);
  boldInput.addEventListener('change', () => {
    variable.style.bold = boldInput.checked;
    updateHiddenInput();
    renderOverlay();
  });

  const italicInput = document.createElement('input');
  italicInput.type = 'checkbox';
  italicInput.setAttribute('aria-label', i18n.italic);
  italicInput.checked = Boolean(variable.style.italic);
  italicInput.addEventListener('change', () => {
    variable.style.italic = italicInput.checked;
    updateHiddenInput();
    renderOverlay();
  });

  const styleRow = document.createElement('div');
  styleRow.className = 'acg-style-row';
  styleRow.append(`${i18n.font} `, fontSelect, ` ${i18n.size} `, sizeInput, ` ${i18n.color} `, colorInput);

  const styleRow2 = document.createElement('div');
  styleRow2.className = 'acg-style-row acg-style-row-compact';
  styleRow2.append(`${i18n.align} `, alignSelect, ` ${i18n.bold} `, boldInput, ` ${i18n.italic} `, italicInput);

  styleCell.appendChild(styleRow);
  styleCell.appendChild(styleRow2);

  const typeRow = createTypeSpecificRow(variable);
  if (typeRow.childNodes.length > 0) {
    styleCell.appendChild(typeRow);
  }

  if (variable.type === 'select') {
    const selectInfo = document.createElement('div');
    selectInfo.className = 'acg-style-row';
    const selectInfoText = document.createElement('em');
    selectInfoText.style.color = '#666';
    selectInfoText.style.fontSize = '11px';
    selectInfoText.textContent = sprintfCount(
      i18n.optionsSummarySingular,
      i18n.optionsSummaryPlural,
      variable.options.length,
    );
    selectInfo.appendChild(selectInfoText);
    styleCell.appendChild(selectInfo);
  }

  return styleCell;
};

const createTypeSpecificRow = (variable) => {
  const typeRow = document.createElement('div');
  typeRow.className = 'acg-style-row';

  if (variable.type === 'date') {
    const dateSelect = buildSelect(dateFormats, variable.date_format);
    dateSelect.setAttribute('aria-label', i18n.format);
    dateSelect.addEventListener('change', () => {
      variable.date_format = dateSelect.value;
      updateHiddenInput();
    });
    typeRow.append(`${i18n.format} `, dateSelect);
  }

  if (variable.type === 'auto') {
    const autoSelect = buildSelect(autoTypes, variable.auto_type);
    autoSelect.setAttribute('aria-label', i18n.auto);
    autoSelect.addEventListener('change', () => {
      variable.auto_type = autoSelect.value;
      updateHiddenInput();
    });
    typeRow.append(`${i18n.auto} `, autoSelect);
  }

  if (variable.type === 'image') {
    const maxW = document.createElement('input');
    maxW.type = 'number';
    maxW.setAttribute('aria-label', i18n.imageMaxWidth);
    maxW.min = '1';
    maxW.value = variable.image_max_width;
    maxW.addEventListener('input', () => {
      variable.image_max_width = Number(maxW.value) || 0;
      updateHiddenInput();
    });

    const maxH = document.createElement('input');
    maxH.type = 'number';
    maxH.setAttribute('aria-label', i18n.imageMaxHeight);
    maxH.min = '1';
    maxH.value = variable.image_max_height;
    maxH.addEventListener('input', () => {
      variable.image_max_height = Number(maxH.value) || 0;
      updateHiddenInput();
    });

    typeRow.append(`${i18n.imageMaxWidth} `, maxW, ` ${i18n.imageMaxHeight} `, maxH);
  }

  return typeRow;
};

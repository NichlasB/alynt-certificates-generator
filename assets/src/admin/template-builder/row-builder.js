import { state, dom } from './state.js';
import { dateFormats, autoTypes, variableTypes, alignOptions } from './constants.js';
import { formatPercentage, parsePercentageInput } from './coordinates.js';
import { updateHiddenInput } from './rest-save.js';
import { buildSelect, addFontsToGroup } from './ui-helpers.js';

export const renderRow = (variable, index, renderVariables, renderOverlay) => {
  const row = document.createElement('tr');
  row.dataset.varId = variable.id;
  row.dataset.varIndex = index;
  row.draggable = true;

  const dragCell = document.createElement('td');
  dragCell.className = 'acg-drag-handle';
  dragCell.innerHTML = '&#9776;';
  dragCell.title = 'Drag to reorder';

  const labelCell = document.createElement('td');
  const labelInput = document.createElement('input');
  labelInput.type = 'text';
  labelInput.value = variable.label;
  labelInput.addEventListener('input', () => {
    variable.label = labelInput.value;
    updateHiddenInput();
    const marker = dom.overlay?.querySelector(`[data-var-id="${variable.id}"]`);
    if (marker) {
      marker.textContent = variable.label || variable.key;
    }
  });
  labelCell.appendChild(labelInput);

  const keyCell = document.createElement('td');
  const keyInput = document.createElement('input');
  keyInput.type = 'text';
  keyInput.value = variable.key;
  keyInput.addEventListener('input', () => {
    variable.key = keyInput.value;
    updateHiddenInput();
  });
  keyCell.appendChild(keyInput);

  const typeCell = document.createElement('td');
  const typeSelect = buildSelect(variableTypes, variable.type);
  typeSelect.addEventListener('change', () => {
    variable.type = typeSelect.value;
    updateHiddenInput();
    renderVariables();
  });
  typeCell.appendChild(typeSelect);

  const requiredCell = document.createElement('td');
  const requiredInput = document.createElement('input');
  requiredInput.type = 'checkbox';
  requiredInput.checked = Boolean(variable.required);
  requiredInput.addEventListener('change', () => {
    variable.required = requiredInput.checked;
    updateHiddenInput();
  });
  requiredCell.appendChild(requiredInput);

  const displayCell = document.createElement('td');
  const displayInput = document.createElement('input');
  displayInput.type = 'checkbox';
  displayInput.checked = Boolean(variable.display_on_certificate);
  displayInput.addEventListener('change', () => {
    variable.display_on_certificate = displayInput.checked;
    updateHiddenInput();
    renderOverlay();
  });
  displayCell.appendChild(displayInput);

  const styleCell = createStyleCell(variable, renderOverlay);
  const positionCell = createPositionCell(variable, renderOverlay);
  const actionCell = createActionCell(variable, renderVariables);

  row.append(dragCell, labelCell, keyCell, typeCell, requiredCell, displayCell, styleCell, positionCell, actionCell);
  dom.tableBody.appendChild(row);

  if (variable.type === 'select') {
    renderOptionsRow(variable);
  }
};

const createStyleCell = (variable, renderOverlay) => {
  const styleCell = document.createElement('td');
  styleCell.className = 'acg-variable-style';

  const fontSelect = document.createElement('select');

  const systemFontList = state.allFonts.filter((f) => f.type === 'system' || !f.type);
  const globalFontList = state.allFonts.filter((f) => f.type === 'global');
  const templateFontList = state.allFonts.filter((f) => f.type === 'template');

  addFontsToGroup(fontSelect, systemFontList, 'System Fonts', variable.style.font_family);
  addFontsToGroup(fontSelect, globalFontList, 'Custom Fonts (Global)', variable.style.font_family);
  addFontsToGroup(fontSelect, templateFontList, 'Custom Fonts (Template)', variable.style.font_family);

  fontSelect.addEventListener('change', () => {
    variable.style.font_family = fontSelect.value;
    updateHiddenInput();
    renderOverlay();
  });

  const sizeInput = document.createElement('input');
  sizeInput.type = 'number';
  sizeInput.min = '8';
  sizeInput.value = variable.style.font_size;
  sizeInput.addEventListener('input', () => {
    variable.style.font_size = Number(sizeInput.value) || 0;
    updateHiddenInput();
    renderOverlay();
  });

  const colorInput = document.createElement('input');
  colorInput.type = 'color';
  colorInput.value = variable.style.color;
  colorInput.addEventListener('input', () => {
    variable.style.color = colorInput.value;
    updateHiddenInput();
    renderOverlay();
  });

  const alignSelect = buildSelect(alignOptions, variable.style.align || 'left');
  alignSelect.addEventListener('change', () => {
    variable.style.align = alignSelect.value;
    updateHiddenInput();
    renderOverlay();
  });

  const boldInput = document.createElement('input');
  boldInput.type = 'checkbox';
  boldInput.checked = Boolean(variable.style.bold);
  boldInput.addEventListener('change', () => {
    variable.style.bold = boldInput.checked;
    updateHiddenInput();
    renderOverlay();
  });

  const italicInput = document.createElement('input');
  italicInput.type = 'checkbox';
  italicInput.checked = Boolean(variable.style.italic);
  italicInput.addEventListener('change', () => {
    variable.style.italic = italicInput.checked;
    updateHiddenInput();
    renderOverlay();
  });

  const styleRow = document.createElement('div');
  styleRow.className = 'acg-style-row';
  styleRow.append('Font ', fontSelect, ' Size ', sizeInput, ' Color ', colorInput);

  const styleRow2 = document.createElement('div');
  styleRow2.className = 'acg-style-row acg-style-row-compact';
  styleRow2.append('Align ', alignSelect, ' B ', boldInput, ' I ', italicInput);

  styleCell.appendChild(styleRow);
  styleCell.appendChild(styleRow2);

  const typeRow = createTypeSpecificRow(variable);
  if (typeRow.childNodes.length > 0) {
    styleCell.appendChild(typeRow);
  }

  if (variable.type === 'select') {
    const selectInfo = document.createElement('div');
    selectInfo.className = 'acg-style-row';
    selectInfo.innerHTML = `<em style="color: #666; font-size: 11px;">Options: ${variable.options.length} (edit below)</em>`;
    styleCell.appendChild(selectInfo);
  }

  return styleCell;
};

const createTypeSpecificRow = (variable) => {
  const typeRow = document.createElement('div');
  typeRow.className = 'acg-style-row';

  if (variable.type === 'date') {
    const dateSelect = buildSelect(dateFormats, variable.date_format);
    dateSelect.addEventListener('change', () => {
      variable.date_format = dateSelect.value;
      updateHiddenInput();
    });
    typeRow.append('Format ', dateSelect);
  }

  if (variable.type === 'auto') {
    const autoSelect = buildSelect(autoTypes, variable.auto_type);
    autoSelect.addEventListener('change', () => {
      variable.auto_type = autoSelect.value;
      updateHiddenInput();
    });
    typeRow.append('Auto ', autoSelect);
  }

  if (variable.type === 'image') {
    const maxW = document.createElement('input');
    maxW.type = 'number';
    maxW.min = '1';
    maxW.value = variable.image_max_width;
    maxW.addEventListener('input', () => {
      variable.image_max_width = Number(maxW.value) || 0;
      updateHiddenInput();
    });

    const maxH = document.createElement('input');
    maxH.type = 'number';
    maxH.min = '1';
    maxH.value = variable.image_max_height;
    maxH.addEventListener('input', () => {
      variable.image_max_height = Number(maxH.value) || 0;
      updateHiddenInput();
    });

    typeRow.append('Max W ', maxW, ' Max H ', maxH);
  }

  return typeRow;
};

const createPositionCell = (variable, renderOverlay) => {
  const positionCell = document.createElement('td');

  const positionContainer = document.createElement('div');
  positionContainer.className = 'acg-position-inputs';

  const xRow = document.createElement('div');
  xRow.className = 'acg-position-row';
  const xLabel = document.createElement('label');
  xLabel.textContent = 'X:';
  xLabel.style.marginRight = '4px';

  const xInput = document.createElement('input');
  xInput.type = 'text';
  xInput.value = formatPercentage(variable.x);
  xInput.dataset.field = 'x';
  xInput.style.width = '70px';
  xInput.addEventListener('change', () => {
    variable.x = parsePercentageInput(xInput.value);
    variable.x = Math.max(0, Math.min(1, variable.x));
    xInput.value = formatPercentage(variable.x);
    updateHiddenInput();
    renderOverlay();
  });

  xRow.appendChild(xLabel);
  xRow.appendChild(xInput);

  const yRow = document.createElement('div');
  yRow.className = 'acg-position-row';
  const yLabel = document.createElement('label');
  yLabel.textContent = 'Y:';
  yLabel.style.marginRight = '4px';

  const yInput = document.createElement('input');
  yInput.type = 'text';
  yInput.value = formatPercentage(variable.y);
  yInput.dataset.field = 'y';
  yInput.style.width = '70px';
  yInput.addEventListener('change', () => {
    variable.y = parsePercentageInput(yInput.value);
    variable.y = Math.max(0, Math.min(1, variable.y));
    yInput.value = formatPercentage(variable.y);
    updateHiddenInput();
    renderOverlay();
  });

  yRow.appendChild(yLabel);
  yRow.appendChild(yInput);

  positionContainer.appendChild(xRow);
  positionContainer.appendChild(yRow);
  positionCell.appendChild(positionContainer);

  return positionCell;
};

const createActionCell = (variable, renderVariables) => {
  const actionCell = document.createElement('td');
  const deleteButton = document.createElement('button');
  deleteButton.type = 'button';
  deleteButton.className = 'button';
  deleteButton.textContent = 'Remove';
  deleteButton.addEventListener('click', () => {
    state.variables = state.variables.filter((item) => item.id !== variable.id);
    updateHiddenInput();
    renderVariables();
  });
  actionCell.appendChild(deleteButton);
  return actionCell;
};

const renderOptionsRow = (variable) => {
  const optionsRow = document.createElement('tr');
  optionsRow.className = 'acg-options-row';
  optionsRow.dataset.optionsFor = variable.id;

  const optionsCell = document.createElement('td');
  optionsCell.colSpan = 9;

  const optionsContainer = document.createElement('div');
  optionsContainer.className = 'acg-select-options';

  const optionsHeader = document.createElement('div');
  optionsHeader.style.display = 'flex';
  optionsHeader.style.justifyContent = 'space-between';
  optionsHeader.style.alignItems = 'center';
  optionsHeader.style.marginBottom = '10px';

  const optionsLabel = document.createElement('strong');
  optionsLabel.textContent = 'Dropdown Options:';

  const optionsCount = document.createElement('span');
  optionsCount.style.color = '#666';
  optionsCount.textContent = ` (${variable.options.length} options)`;

  optionsHeader.appendChild(optionsLabel);
  optionsHeader.appendChild(optionsCount);
  optionsContainer.appendChild(optionsHeader);

  const optionsList = document.createElement('div');
  optionsList.className = 'acg-options-list';

  const renderOptionsList = () => {
    optionsList.innerHTML = '';
    optionsCount.textContent = ` (${variable.options.length} options)`;
    variable.options.forEach((option, optIndex) => {
      const optionRow = document.createElement('div');
      optionRow.className = 'acg-option-row';

      const labelInput = document.createElement('input');
      labelInput.type = 'text';
      labelInput.placeholder = 'Option text (shown in dropdown and on certificate)';
      labelInput.value = option.label || '';
      labelInput.addEventListener('input', () => {
        variable.options[optIndex].label = labelInput.value;
        updateHiddenInput();
      });

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'button button-small';
      removeBtn.textContent = 'Remove';
      removeBtn.addEventListener('click', () => {
        variable.options.splice(optIndex, 1);
        updateHiddenInput();
        renderOptionsList();
      });

      optionRow.append(labelInput, removeBtn);
      optionsList.appendChild(optionRow);
    });
  };

  renderOptionsList();
  optionsContainer.appendChild(optionsList);

  const addOptionBtn = document.createElement('button');
  addOptionBtn.type = 'button';
  addOptionBtn.className = 'button button-secondary';
  addOptionBtn.textContent = '+ Add Option';
  addOptionBtn.style.marginTop = '10px';
  addOptionBtn.addEventListener('click', () => {
    variable.options.push({ label: '' });
    updateHiddenInput();
    renderOptionsList();
  });

  optionsContainer.appendChild(addOptionBtn);
  optionsCell.appendChild(optionsContainer);
  optionsRow.appendChild(optionsCell);
  dom.tableBody.appendChild(optionsRow);
};

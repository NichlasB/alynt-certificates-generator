import { state, dom } from './state.js';
import { variableTypes } from './constants.js';
import { formatPercentage, parsePercentageInput } from './coordinates.js';
import { updateHiddenInput } from './rest-save.js';
import { buildSelect } from './ui-helpers.js';
import { renderOptionsRow } from './row-options.js';
import { createStyleCell } from './row-style-cell.js';
import { i18n, sprintfString } from './i18n.js';
import { moveVariable } from './row-drag.js';

export const renderRow = (variable, index, renderVariables, renderOverlay) => {
  const row = document.createElement('tr');
  row.dataset.varId = variable.id;
  row.dataset.varIndex = index;
  row.draggable = true;

  const dragCell = document.createElement('td');
  dragCell.className = 'acg-drag-handle';
  dragCell.title = i18n.dragToReorder;
  dragCell.appendChild(createReorderControls(variable, index, renderVariables));

  const labelCell = document.createElement('td');
  const labelInput = document.createElement('input');
  labelInput.type = 'text';
  labelInput.value = variable.label;
  labelInput.setAttribute('aria-label', sprintfString(i18n.labelField, variable.label || variable.key));
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
  keyInput.setAttribute('aria-label', sprintfString(i18n.keyField, variable.label || variable.key));
  keyInput.addEventListener('input', () => {
    variable.key = keyInput.value;
    updateHiddenInput();
  });
  keyCell.appendChild(keyInput);

  const typeCell = document.createElement('td');
  const typeSelect = buildSelect(variableTypes, variable.type);
  typeSelect.setAttribute('aria-label', sprintfString(i18n.typeField, variable.label || variable.key));
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
  requiredInput.setAttribute('aria-label', sprintfString(i18n.requiredField, variable.label || variable.key));
  requiredInput.addEventListener('change', () => {
    variable.required = requiredInput.checked;
    updateHiddenInput();
  });
  requiredCell.appendChild(requiredInput);

  const displayCell = document.createElement('td');
  const displayInput = document.createElement('input');
  displayInput.type = 'checkbox';
  displayInput.checked = Boolean(variable.display_on_certificate);
  displayInput.setAttribute('aria-label', sprintfString(i18n.displayField, variable.label || variable.key));
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

const createReorderControls = (variable, index, renderVariables) => {
  const controls = document.createElement('div');
  controls.className = 'acg-reorder-controls';

  const upButton = createReorderButton(
    i18n.moveVariableUp,
    'up',
    index === 0,
    () => moveVariable(variable.id, -1, renderVariables),
  );

  const downButton = createReorderButton(
    i18n.moveVariableDown,
    'down',
    index >= state.variables.length - 1,
    () => moveVariable(variable.id, 1, renderVariables),
  );

  controls.append(upButton, downButton);
  return controls;
};

const createReorderButton = (label, direction, disabled, onClick) => {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'button-link acg-reorder-button';
  button.dataset.reorderDirection = direction;
  button.innerHTML = direction === 'up' ? '&uarr;' : '&darr;';
  button.setAttribute('aria-label', label);
  button.disabled = disabled;
  button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  button.addEventListener('click', onClick);

  return button;
};

const createPositionCell = (variable, renderOverlay) => {
  const positionCell = document.createElement('td');

  const positionContainer = document.createElement('div');
  positionContainer.className = 'acg-position-inputs';

  const xRow = document.createElement('div');
  xRow.className = 'acg-position-row';
  const xLabel = document.createElement('label');
  xLabel.textContent = i18n.xCoordinate;
  xLabel.style.marginRight = '4px';

  const xInput = document.createElement('input');
  xInput.type = 'text';
  xInput.setAttribute('aria-label', i18n.xCoordinate);
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
  yLabel.textContent = i18n.yCoordinate;
  yLabel.style.marginRight = '4px';

  const yInput = document.createElement('input');
  yInput.type = 'text';
  yInput.setAttribute('aria-label', i18n.yCoordinate);
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
  deleteButton.className = 'button-link button-link-delete';
  deleteButton.textContent = i18n.remove;
  deleteButton.addEventListener('click', () => {
    const variableLabel = variable.label || variable.key || i18n.variableLabel;
    if (!window.confirm(sprintfString(i18n.confirmRemoveVariable, variableLabel))) {
      return;
    }
    state.variables = state.variables.filter((item) => item.id !== variable.id);
    updateHiddenInput();
    renderVariables();
  });
  actionCell.appendChild(deleteButton);
  return actionCell;
};

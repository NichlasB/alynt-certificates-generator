import { state, dom } from './state.js';
import { updateHiddenInput } from './rest-save.js';

export const handleDragStart = (event) => {
  const row = event.target.closest('tr[data-var-id]');
  if (!row) {
    return;
  }
  state.draggedRow = row;
  state.draggedIndex = parseInt(row.dataset.varIndex, 10);
  row.classList.add('acg-dragging');
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', row.dataset.varId);
};

export const handleDragOver = (event) => {
  event.preventDefault();
  event.dataTransfer.dropEffect = 'move';
  const row = event.target.closest('tr[data-var-id]');
  if (row && row !== state.draggedRow) {
    dom.tableBody.querySelectorAll('tr.acg-drag-over').forEach((r) => r.classList.remove('acg-drag-over'));
    row.classList.add('acg-drag-over');
  }
};

export const handleDragLeave = (event) => {
  const row = event.target.closest('tr[data-var-id]');
  if (row) {
    row.classList.remove('acg-drag-over');
  }
};

export const handleDrop = (event, renderVariables) => {
  event.preventDefault();
  const targetRow = event.target.closest('tr[data-var-id]');
  if (!targetRow || targetRow === state.draggedRow || state.draggedIndex < 0) {
    return;
  }

  const targetIndex = parseInt(targetRow.dataset.varIndex, 10);
  if (Number.isNaN(targetIndex) || targetIndex === state.draggedIndex) {
    return;
  }

  const [movedVariable] = state.variables.splice(state.draggedIndex, 1);
  state.variables.splice(targetIndex, 0, movedVariable);

  updateHiddenInput();
  renderVariables();
};

export const handleDragEnd = () => {
  if (state.draggedRow) {
    state.draggedRow.classList.remove('acg-dragging');
  }
  dom.tableBody.querySelectorAll('tr.acg-drag-over').forEach((r) => r.classList.remove('acg-drag-over'));
  state.draggedRow = null;
  state.draggedIndex = -1;
};

export const attachRowDragListeners = (renderVariables) => {
  dom.tableBody.querySelectorAll('tr[data-var-id]').forEach((row) => {
    row.addEventListener('dragstart', handleDragStart);
    row.addEventListener('dragover', handleDragOver);
    row.addEventListener('dragleave', handleDragLeave);
    row.addEventListener('drop', (e) => handleDrop(e, renderVariables));
    row.addEventListener('dragend', handleDragEnd);
  });
};

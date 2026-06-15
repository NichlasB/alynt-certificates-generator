import { dom } from './state.js';
import { updateHiddenInput } from './rest-save.js';
import { i18n, sprintfCount } from './i18n.js';

export const renderOptionsRow = (variable) => {
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
  optionsLabel.textContent = i18n.dropdownOptions;

  const optionsCount = document.createElement('span');
  optionsCount.style.color = '#666';
  optionsCount.textContent = ` ${sprintfCount(i18n.optionCountSingular, i18n.optionCountPlural, variable.options.length)}`;

  optionsHeader.appendChild(optionsLabel);
  optionsHeader.appendChild(optionsCount);
  optionsContainer.appendChild(optionsHeader);

  const optionsList = document.createElement('div');
  optionsList.className = 'acg-options-list';

  const renderOptionsList = () => {
    optionsList.innerHTML = '';
    optionsCount.textContent = ` ${sprintfCount(i18n.optionCountSingular, i18n.optionCountPlural, variable.options.length)}`;
    variable.options.forEach((option, optIndex) => {
      const optionRow = document.createElement('div');
      optionRow.className = 'acg-option-row';

      const labelInput = document.createElement('input');
      labelInput.type = 'text';
      labelInput.placeholder = i18n.optionTextPlaceholder;
      labelInput.setAttribute('aria-label', i18n.optionTextPlaceholder);
      labelInput.value = option.label || '';
      labelInput.addEventListener('input', () => {
        variable.options[optIndex].label = labelInput.value;
        updateHiddenInput();
      });

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'button-link button-link-delete';
      removeBtn.textContent = i18n.remove;
      removeBtn.addEventListener('click', () => {
        if (!window.confirm(i18n.confirmRemoveOption)) {
          return;
        }
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
  addOptionBtn.textContent = i18n.addOption;
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

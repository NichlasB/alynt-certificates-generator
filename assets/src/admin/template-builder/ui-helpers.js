export const buildSelect = (options, value) => {
  const select = document.createElement('select');
  let hasSelected = false;
  options.forEach((option) => {
    const opt = document.createElement('option');
    opt.value = option.value;
    opt.textContent = option.label;
    if (option.value === value) {
      opt.selected = true;
      hasSelected = true;
    }
    select.appendChild(opt);
  });
  if (!hasSelected && options.length > 0) {
    select.options[0].selected = true;
  }
  return select;
};

export const addFontsToGroup = (fontSelect, fonts, groupLabel, selectedFamily) => {
  if (fonts.length === 0) {
    return;
  }
  const optgroup = document.createElement('optgroup');
  optgroup.label = groupLabel;
  fonts.forEach((font) => {
    const opt = document.createElement('option');
    opt.value = font.family;
    opt.textContent = font.family;
    opt.selected = font.family === selectedFamily;
    optgroup.appendChild(opt);
  });
  fontSelect.appendChild(optgroup);
};

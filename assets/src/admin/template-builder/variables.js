import { migrateCoordinates } from './coordinates.js';

export const parseVariables = (value) => {
  if (!value) {
    return [];
  }
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

export const ensureDefaults = (variable, index) => {
  const withDefaults = {
    id: variable.id || `var_${Date.now()}_${index}`,
    label: variable.label || `Variable ${index + 1}`,
    key: variable.key || `variable_${index + 1}`,
    type: variable.type || 'text',
    required: variable.required ?? true,
    display_on_certificate: variable.display_on_certificate ?? true,
    x: Number.isFinite(variable.x) ? variable.x : 0,
    y: Number.isFinite(variable.y) ? variable.y : 0,
    style: {
      font_family: variable.style?.font_family || 'Arial',
      font_size: variable.style?.font_size || 24,
      color: variable.style?.color || '#000000',
      align: variable.style?.align || 'left',
      bold: variable.style?.bold || false,
      italic: variable.style?.italic || false,
    },
    date_format: variable.date_format || 'Y-m-d',
    auto_type: variable.auto_type || 'certificate_id',
    image_max_width: variable.image_max_width || 300,
    image_max_height: variable.image_max_height || 300,
    coord_mode: variable.coord_mode || '',
    options: Array.isArray(variable.options) ? variable.options : [],
  };

  return migrateCoordinates(withDefaults);
};

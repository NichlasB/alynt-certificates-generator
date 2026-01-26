import { systemFonts } from './constants.js';

export const state = {
  variables: [],
  saveTimeout: null,
  initialized: false,
  migratedOnLoad: false,
  authoritativeWidth: 0,
  authoritativeHeight: 0,
  customFonts: {
    global: [],
    template: [],
  },
  allFonts: [...systemFonts],
  draggedRow: null,
  draggedIndex: -1,
};

export const dom = {
  builder: null,
  variablesInput: null,
  previewImage: null,
  overlay: null,
  tableBody: null,
  addButton: null,
  imageButton: null,
  imageIdInput: null,
  imagePreview: null,
};

export const initDom = () => {
  dom.builder = document.getElementById('acg-template-builder');
  if (!dom.builder) {
    return false;
  }

  dom.variablesInput = document.getElementById('acg_template_variables_input');
  dom.previewImage = document.getElementById('acg-template-image');
  dom.overlay = document.getElementById('acg-template-overlay');
  dom.tableBody = document.getElementById('acg-template-variables-body');
  dom.addButton = document.getElementById('acg-add-variable');
  dom.imageButton = document.getElementById('acg_template_select_image');
  dom.imageIdInput = document.getElementById('acg_template_image_id');
  dom.imagePreview = document.getElementById('acg_template_image_preview');

  state.authoritativeWidth = parseInt(dom.builder.dataset.imageWidth, 10) || 0;
  state.authoritativeHeight = parseInt(dom.builder.dataset.imageHeight, 10) || 0;

  return true;
};

export const updatePreviewRefs = () => {
  dom.previewImage = document.getElementById('acg-template-image');
  dom.overlay = document.getElementById('acg-template-overlay');
};

const fallbackStrings = {
  addOption: '+ Add Option',
  align: 'Align',
  alignCenter: 'Center',
  alignLeft: 'Left',
  alignRight: 'Right',
  auto: 'Auto',
  autoCertificateId: 'Certificate ID',
  autoGenerationDate: 'Generation Date',
  bold: 'B',
  color: 'Color',
  confirmRemoveOption: 'Remove this dropdown option? This action cannot be undone.',
  confirmRemoveVariable: 'Remove %s from this template? This action cannot be undone.',
  customFontsGlobal: 'Custom Fonts (Global)',
  customFontsTemplate: 'Custom Fonts (Template)',
  dateFormatDayMonthYear: 'DD/MM/YYYY',
  dateFormatFullMonthDayYear: 'Month DD, YYYY',
  dateFormatIso: 'YYYY-MM-DD',
  dateFormatMonthDayYear: 'MM/DD/YYYY',
  dateFormatDayFullMonthYear: 'DD Month YYYY',
  dragToReorder: 'Drag to reorder',
  dropdownOptions: 'Dropdown Options:',
  format: 'Format',
  font: 'Font',
  imageMaxHeight: 'Max H',
  imageMaxWidth: 'Max W',
  keyField: 'Key for %s',
  labelField: 'Label for %s',
  optionCountPlural: '(%d options)',
  optionCountSingular: '(%d option)',
  optionTextPlaceholder: 'Option text (shown in dropdown and on certificate)',
  optionsSummaryPlural: 'Options: %d (edit below)',
  optionsSummarySingular: 'Option: %d (edit below)',
  remove: 'Remove',
  selectCertificateTemplate: 'Select certificate template',
  markerPositionUpdated: 'Marker moved to %s.',
  moveMarkerInstructions: 'Move %s. Use arrow keys to move by 1 pixel, or Shift plus arrow keys to move by 10 pixels.',
  moveVariableDown: 'Move variable down',
  moveVariableUp: 'Move variable up',
  variable: 'Variable',
  variableMoved: 'Variable moved to position %d.',
  size: 'Size',
  systemFonts: 'System Fonts',
  displayField: 'Display %s on certificate',
  requiredField: '%s is required',
  typeField: 'Type for %s',
  typeAuto: 'Auto',
  typeDate: 'Date',
  typeImage: 'Image',
  typeSelect: 'Select',
  typeText: 'Text',
  useThisImage: 'Use this image',
  variableLabel: 'Variable %d',
  xCoordinate: 'X:',
  yCoordinate: 'Y:',
  italic: 'I',
};

export const i18n = {
  ...fallbackStrings,
  ...(window.acgAdmin?.i18n || {}),
};

export const sprintfNumber = (template, value) => (
  String(template || '').replace('%d', String(value))
);

export const sprintfCount = (singular, plural, count) => (
  sprintfNumber(count === 1 ? singular : plural, count)
);

export const sprintfString = (template, value) => (
  String(template || '').replace('%s', String(value))
);

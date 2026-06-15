import { i18n } from './i18n.js';

export const systemFonts = [
  { family: 'Arial', slug: 'arial' },
  { family: 'Helvetica', slug: 'helvetica' },
  { family: 'Times New Roman', slug: 'times_new_roman' },
  { family: 'Georgia', slug: 'georgia' },
  { family: 'Courier New', slug: 'courier_new' },
  { family: 'Verdana', slug: 'verdana' },
];

export const dateFormats = [
  { value: 'Y-m-d', label: i18n.dateFormatIso },
  { value: 'm/d/Y', label: i18n.dateFormatMonthDayYear },
  { value: 'd/m/Y', label: i18n.dateFormatDayMonthYear },
  { value: 'F d, Y', label: i18n.dateFormatFullMonthDayYear },
  { value: 'd F Y', label: i18n.dateFormatDayFullMonthYear },
];

export const autoTypes = [
  { value: 'certificate_id', label: i18n.autoCertificateId },
  { value: 'generation_date', label: i18n.autoGenerationDate },
];

export const variableTypes = [
  { value: 'text', label: i18n.typeText },
  { value: 'date', label: i18n.typeDate },
  { value: 'select', label: i18n.typeSelect },
  { value: 'image', label: i18n.typeImage },
  { value: 'auto', label: i18n.typeAuto },
];

export const alignOptions = [
  { value: 'left', label: i18n.alignLeft },
  { value: 'center', label: i18n.alignCenter },
  { value: 'right', label: i18n.alignRight },
];

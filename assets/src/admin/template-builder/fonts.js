import { systemFonts } from './constants.js';
import { state } from './state.js';

export const fetchAvailableFonts = async (onComplete) => {
  if (!window.acgAdmin || !window.acgAdmin.restUrl) {
    return;
  }

  const templateId = window.acgAdmin.postId || 0;
  const url = `${window.acgAdmin.restUrl}/fonts${templateId ? `?template_id=${templateId}` : ''}`;

  try {
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': window.acgAdmin.restNonce || '',
      },
    });

    if (!response.ok) {
      return;
    }

    const data = await response.json();

    state.customFonts.global = data.global || [];
    state.customFonts.template = data.template || [];

    state.allFonts = [
      ...systemFonts.map((f) => ({ ...f, type: 'system' })),
      ...state.customFonts.global.map((f) => ({ ...f, type: 'global' })),
      ...state.customFonts.template.map((f) => ({ ...f, type: 'template' })),
    ];

    if (onComplete) {
      onComplete();
    }
  } catch {
    // Silently fail - system fonts will still be available
  }
};

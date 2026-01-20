const bulkProgress = document.getElementById('acg-bulk-progress');

if (bulkProgress && window.acgAdmin?.restUrl) {
  const bulkId = bulkProgress.dataset.bulkId;
  const processedEl = bulkProgress.querySelector('[data-progress="processed"]');
  const failedEl = bulkProgress.querySelector('[data-progress="failed"]');
  const totalEl = bulkProgress.querySelector('[data-progress="total"]');

  const updateProgress = (data) => {
    if (!data) {
      return;
    }
    if (processedEl) {
      processedEl.textContent = data.processed ?? 0;
    }
    if (failedEl) {
      failedEl.textContent = data.failed ?? 0;
    }
    if (totalEl) {
      totalEl.textContent = data.total ?? 0;
    }
  };

  const fetchStatus = () => {
    fetch(`${window.acgAdmin.restUrl}/bulk/${bulkId}/status`, {
      headers: {
        'X-WP-Nonce': window.acgAdmin.restNonce || '',
      },
    })
      .then((response) => response.json())
      .then((data) => updateProgress(data))
      .catch(() => {});
  };

  fetchStatus();
  setInterval(fetchStatus, 5000);
}

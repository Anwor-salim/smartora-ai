<template>
  <div class="inv-footer">
    <!-- Divider -->
    <div class="inv-footer__divider"></div>

    <div class="inv-footer__grid">
      <!-- Notes -->
      <div v-if="notes" class="inv-footer__section">
        <h5 class="inv-footer__section-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          ملاحظات
        </h5>
        <p class="inv-footer__notes-text">{{ notes }}</p>
      </div>

      <!-- Terms -->
      <div class="inv-footer__section">
        <h5 class="inv-footer__section-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          الشروط والأحكام
        </h5>
        <ul class="inv-footer__terms">
          <li>تعتبر هذه الفاتورة سنداً رسمياً لاستلام البضاعة.</li>
          <li>البضاعة المباعة لا ترد ولا تستبدل بعد مرور ٧ أيام.</li>
          <li>يجب سداد الفواتير الآجلة خلال ٣٠ يوماً من تاريخ الإصدار.</li>
        </ul>
      </div>
    </div>

    <!-- Thank You -->
    <div class="inv-footer__thank-you">
      <div class="inv-footer__thank-you-line"></div>
      <p class="inv-footer__thank-you-ar">نشكركم لتعاملكم معنا</p>
      <p class="inv-footer__thank-you-en">Thank you for your business</p>
      <div class="inv-footer__thank-you-line"></div>
    </div>

    <!-- Powered by -->
    <p class="inv-footer__powered">تم إنشاء هذه الفاتورة إلكترونياً بواسطة نظام رابط فارما ERP</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  notes?: string
}>()
</script>

<style scoped>
.inv-footer {
  margin-top: 3rem;
}

.inv-footer__divider {
  height: 1px;
  background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
  margin-bottom: 2rem;
}

.inv-footer__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 2.5rem;
}

.inv-footer__section-title {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 0.65rem;
}

.inv-footer__section-title svg {
  color: #94a3b8;
}

.inv-footer__notes-text {
  font-size: 0.82rem;
  color: #64748b;
  line-height: 1.7;
  font-style: italic;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 8px;
  padding: 0.75rem 1rem;
}

.inv-footer__terms {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.inv-footer__terms li {
  font-size: 0.75rem;
  color: #94a3b8;
  line-height: 1.6;
  padding-right: 1rem;
  position: relative;
}

.inv-footer__terms li::before {
  content: '•';
  position: absolute;
  right: 0;
  color: var(--color-primary);
  font-weight: 900;
}

/* Thank You */
.inv-footer__thank-you {
  text-align: center;
  padding: 1.25rem 0;
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.inv-footer__thank-you-line {
  flex: 1;
  height: 1px;
  background: #e2e8f0;
}

.inv-footer__thank-you-ar {
  font-size: 1.1rem;
  font-weight: 800;
  color: #cbd5e1;
  white-space: nowrap;
}

.inv-footer__thank-you-en {
  font-size: 0.7rem;
  color: #e2e8f0;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  font-weight: 700;
  white-space: nowrap;
}

.inv-footer__powered {
  text-align: center;
  font-size: 0.65rem;
  color: #cbd5e1;
  font-weight: 700;
  margin-top: 0.75rem;
}

@media (max-width: 640px) {
  .inv-footer__grid {
    grid-template-columns: 1fr;
  }
}
</style>

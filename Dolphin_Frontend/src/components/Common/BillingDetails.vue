<template>
  <MainLayout>
    <div class="page">
      <div class="table-outer">
        <div class="table-card">
          <div class="billing-title">Billing Details (Current Subscription)</div>

          <div class="billing-plan-box">
            <template v-if="subscription">
              <div class="plan-left">
                <div class="plan-name">
                  {{ subscription.plan?.name || 'Plan' }}
                </div>
                <div class="plan-price" style="text-align: left">
                  {{
                    subscription.plan?.currency &&
                    subscription.plan.currency.toLowerCase() === 'usd'
                      ? '$'
                      : ''
                  }}{{ subscription.plan?.amount || subscription.latest_amount_paid || '' }}
                  <span class="plan-card-period"
                    >/{{ subscription.plan?.interval === 'monthly' ? 'Month' : 'Annual' }}</span
                  >
                </div>
              </div>

              <div class="plan-right">
                <div class="plan-meta-row">
                  <div>
                    Subscription Start :
                    <b>{{
                      subscription.start
                        ? formatDate(subscription.start)
                        : subscription.started_at
                          ? formatDate(subscription.started_at)
                          : 'N/A'
                    }}</b>
                  </div>
                </div>
                <div class="plan-meta-row">
                  <div>
                    Subscription End :
                    <b>{{
                      subscription.end
                        ? formatDate(subscription.end)
                        : subscription.ends_at
                          ? formatDate(subscription.ends_at)
                          : 'N/A'
                    }}</b>
                  </div>
                </div>
                <div class="plan-meta-row small">
                  (Next bill on
                  {{
                    subscription.current_period_end
                      ? formatDate(subscription.current_period_end)
                      : 'N/A'
                  }})
                </div>
              </div>
            </template>
            <template v-else>
              <div class="plan-left">
                <div class="plan-name">No active subscription</div>
                <div class="plan-price">—</div>
              </div>
              <div class="plan-right">
                <div class="plan-meta-row">Please choose a plan to get started.</div>
              </div>
            </template>
          </div>

          <div class="billing-title">Billing History</div>
          <div class="table-container">
            <div class="table-scroll">
              <table class="table">
                <!-- Reuse shared table header component for consistent styling -->
                <TableHeader :columns="columns" />
                <tbody>
                  <tr
                    v-for="(invoice, idx) in invoices"
                    :key="invoice.invoice_id || invoice.subscription_id || idx"
                  >
                    <td data-label="Payment Method">
                      {{ invoice.payment_method || subscription?.payment_method?.label || '-' }}
                    </td>
                    <td data-label="Payment Date">
                      {{ invoice.paymentDate ? formatDate(invoice.paymentDate) : '' }}
                    </td>
                    <td data-label="Subscription End">
                      {{ invoice.subscriptionEnd ? formatDate(invoice.subscriptionEnd) : '' }}
                    </td>
                    <td data-label="Amount">
                      {{ invoice.currency && invoice.currency.toLowerCase() === 'usd' ? '$' : ''
                      }}{{ invoice.amount ?? invoice.amount_paid ?? invoice.amount_due ?? '' }}
                    </td>
                    <td data-label="Receipt">
                      <a v-if="invoice.pdfUrl" :href="invoice.pdfUrl" target="_blank" rel="noopener"
                        >View Receipt</a
                      >
                      <a
                        v-else-if="invoice.hosted_invoice_url"
                        :href="invoice.hosted_invoice_url"
                        target="_blank"
                        rel="noopener"
                        >View Receipt</a
                      >
                      <span v-else>—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script>
import MainLayout from '@/components/layout/MainLayout.vue';
import TableHeader from '@/components/Common/Common_UI/TableHeader.vue';
import {
  getActiveSubscription,
  getInvoices,
  createBillingPortalSession,
} from '@/services/subscription';

export default {
  name: 'BillingDetails',
  components: { MainLayout, TableHeader },
  data() {
    return {
      subscription: null,
      invoices: [],
      columns: [
        { label: 'Payment Method', key: 'payment_method', minWidth: '180px' },
        {
          label: 'Payment Date',
          key: 'paymentDate',
          minWidth: '160px',
          sortable: true,
        },
        {
          label: 'Subscription End',
          key: 'subscriptionEnd',
          minWidth: '160px',
        },
        { label: 'Amount', key: 'amount', minWidth: '120px' },
        { label: 'Receipt', key: 'pdfUrl', minWidth: '140px' },
      ],
      isCreatingPortal: false,
    };
  },
  methods: {
    async loadBillingDetails() {
      try {
        const sub = await getActiveSubscription();
        this.subscription = sub || null;

        let invRes = null;
        try {
          const params = {};
          if (this.subscription && this.subscription.id)
            params.subscription_id = this.subscription.id;
          invRes = await getInvoices(params);
        } catch (e) {
          console.debug && console.debug('Could not fetch invoices for subscription:', e);
          invRes = null;
        }

        if (Array.isArray(invRes)) {
          this.invoices = invRes;
        } else if (invRes && Array.isArray(invRes.data)) {
          this.invoices = invRes.data;
        } else {
          this.invoices = [];
        }

        if ((!this.invoices || this.invoices.length === 0) && this.subscription) {
          this.invoices = [this.synthesizeSubscriptionSummary(this.subscription)];
        }
      } catch (e) {
        console.debug && console.debug('Failed to load billing details:', e);
        this.subscription = null;
        this.invoices = [];
      }
    },

    synthesizeSubscriptionSummary(subscription) {
      const plan = subscription.plan || null;
      return {
        subscription_id: subscription.id || subscription.subscription_id || null,
        plan_id: subscription.plan_id || (plan && plan.id) || null,
        status: subscription.status || null,
        subscriptionEnd:
          subscription.end || subscription.ends_at || subscription.current_period_end || null,
        paymentDate: subscription.start || subscription.started_at || null,
        payment_method:
          subscription.payment_method?.label || subscription.payment_method_label || null,
        amount: plan?.amount || subscription.latest_amount_paid || null,
        currency: plan?.currency || subscription.currency || null,
        pdfUrl: null,
        description: plan ? `${plan.name} subscription` : 'Subscription payment',
      };
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      if (Number.isNaN(d)) return '';
      return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },
    async manageSubscription() {
      this.isCreatingPortal = true;
      try {
        const res = await createBillingPortalSession();
        const url = res?.url || res?.data?.url || null;
        if (url) {
          globalThis.location.href = url;
        } else {
          console.debug && console.debug('Billing portal endpoint did not return a URL:', res);
          alert('Billing portal is not available.');
        }
      } catch (err) {
        console.debug &&
          console.debug(
            'Failed to create billing portal session',
            err && err.message ? err.message : err
          );
        alert('Unable to open billing portal at this time.');
      } finally {
        this.isCreatingPortal = false;
      }
    },
  },
  mounted() {
    this.loadBillingDetails();
  },
};
</script>

<style scoped>
.billing-title {
  font-size: 24px;
  font-weight: 600;
  margin: 24px;
  text-align: left;
}
.billing-plan-box {
  background: #f6f6f6;
  border-radius: 12px;
  padding: 24px 32px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;

  box-sizing: border-box;
  margin: 24px 16px 0 16px;
}
.plan-name {
  font-size: 22px;
  font-weight: 500;
  margin-bottom: 8px;
  text-align: left;
}
.plan-price {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 0;
}
.plan-left {
  flex: 0 0 55%;
}
.plan-right {
  flex: 0 0 45%;
  text-align: right;
}
.plan-meta-row {
  color: #666;
  font-size: 14px;
  margin-bottom: 8px;
}
.plan-meta-row.small {
  font-size: 13px;
  color: #999;
}

/* Table tweaks for billing history */
.table thead th {
  background: #fafafa;
  color: #333;
  font-weight: 600;
  padding: 12px 16px;
  text-align: left;
}
.table tbody td {
  padding: 12px 16px;
  border-top: 1px solid #f0f0f0;
}
.plan-meta {
  text-align: right;
  color: #888;
  font-size: 17px;
  font-weight: 400;
}
.plan-meta b {
  color: #222;
  font-weight: 500;
}
.plan-next {
  font-size: 18px;
  color: #888;
  margin-top: 4px;

  font-weight: 600;
}

.receipt-link {
  color: #0074c2;
  text-decoration: underline;
  font-size: 17px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

/* Responsive styles to match other pages */
@media (max-width: 1400px) {
  .billing-plan-box {
    padding: 18px 8px;
  }
}
@media (max-width: 900px) {
  .billing-plan-box {
    padding: 16px 8px;
    flex-direction: column;
    gap: 14px;
    align-items: center;
    justify-content: center;
    text-align: center;
  }
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-purchase-order-approval-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') {
                    return;
                }

                const requiresWithoutInvoiceConfirmation = form.dataset.requiresWithoutInvoiceConfirmation === '1';
                const requiresDivergenceConfirmation = form.dataset.requiresDivergenceConfirmation === '1';
                const messages = ['Aprovar este pedido e lançar no financeiro/estoque?'];

                if (requiresWithoutInvoiceConfirmation) {
                    messages.push('Este pedido não possui nota fiscal vinculada. Deseja aprovar mesmo assim?');
                }

                if (requiresDivergenceConfirmation) {
                    messages.push('Existem divergências entre o pedido e a nota fiscal vinculada. Confira antes de aprovar. Deseja aprovar mesmo assim?');
                }

                if (!window.confirm(messages.join('\n\n'))) {
                    event.preventDefault();

                    return;
                }

                if (requiresWithoutInvoiceConfirmation && !form.querySelector('[name="confirmar_sem_nota"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'confirmar_sem_nota';
                    input.value = '1';
                    form.appendChild(input);
                }

                if (requiresDivergenceConfirmation && !form.querySelector('[name="confirmar_divergencias"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'confirmar_divergencias';
                    input.value = '1';
                    form.appendChild(input);
                }

                form.dataset.confirmed = '1';
            });
        });
    });
</script>

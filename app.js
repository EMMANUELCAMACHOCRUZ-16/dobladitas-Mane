const { createApp } = Vue;

createApp({
    data() {
        const config = window.dobladitasConfig;
        const cantidades = Object.fromEntries(
            config.guisados.map(guisado => [
                guisado.id,
                Number(config.cantidades[guisado.id] || 0)
            ])
        );

        return {
            guisados: config.guisados,
            cantidades,
            tipoCliente: config.tipoCliente,
            precioPorPieza: 5
        };
    },
    computed: {
        selectedItems() {
            return this.guisados
                .filter(guisado => this.cantidad(guisado.id) > 0)
                .map(guisado => ({
                    ...guisado,
                    cantidad: this.cantidad(guisado.id)
                }));
        },
        totalPiezas() {
            return this.selectedItems.reduce((total, item) => total + item.cantidad, 0);
        },
        esAlumno() {
            return this.tipoCliente === 'Alumno';
        }
    },
    methods: {
        cantidad(id) {
            return Number(this.cantidades[id] || 0);
        },
        cambiarCantidad(id, cambio) {
            this.cantidades[id] = Math.max(0, Math.min(99, this.cantidad(id) + cambio));
        },
        limitarCantidad(id) {
            const cantidad = Number.parseInt(this.cantidades[id], 10);
            this.cantidades[id] = Number.isNaN(cantidad)
                ? 0
                : Math.max(0, Math.min(99, cantidad));
        },
        cambiarTipoCliente(event) {
            this.tipoCliente = event.target.value;
        },
        actualizarCantidades() {
            this.guisados.forEach(guisado => {
                const campo = document.querySelector(
                    `[name="cantidades[${guisado.id}]"]`
                );
                if (campo) {
                    campo.value = this.cantidad(guisado.id);
                }
            });
        },
        money(amount) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN',
                maximumFractionDigits: 2
            }).format(amount);
        }
    }
}).mount('#app');

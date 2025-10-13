<script>
function carniItemsFormState(INITIAL_ITEMS = [], LOCKED = false, getUnitPriceCb = null) {
    return {
        items: (Array.isArray(INITIAL_ITEMS) && INITIAL_ITEMS.length)
            ? INITIAL_ITEMS
            : [{product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0}],
        locked: LOCKED,
        subtotal:0, desc_total:0, tax_total:0, grand:0,
        anyZeroPrice:false,

        init(){ this.sum(); },

        onProductChange(i, ev){
            const it = this.items[i];
            if (!it.descripcion && ev && ev.target) {
                const opt = ev.target.options[ev.target.selectedIndex];
                it.descripcion = (opt?.text || '').trim();
            }
            if (typeof getUnitPriceCb === 'function' && it.product_id) {
                it.precio = getUnitPriceCb(it.product_id) ?? 0;
            }
            this.recalc(i);
        },
        repriceAll(){
            if (typeof getUnitPriceCb !== 'function') return;
            this.items.forEach((it,i)=>{
                if (it.product_id) {
                    it.precio = getUnitPriceCb(it.product_id) ?? 0;
                    this.recalc(i, true);
                }
            });
            this.sum();
        },
        add(){
            if(this.locked) return;
            this.items.push({product_id:'', descripcion:'', cantidad:1, precio:0, descuento:0, iva_pct:0, impuesto:0, total:0});
        },
        remove(i){
            if(this.locked) return;
            this.items.splice(i,1);
            this.sum();
        },
        recalc(i, skipSum=false){
            const it   = this.items[i];
            const line = (+it.cantidad || 0) * (+it.precio || 0);
            const disc = +it.descuento || 0;
            const base = Math.max(line - disc, 0);
            const tax  = ((+it.iva_pct || 0) * 0.01) * base;
            it.impuesto = tax;
            it.total    = base + tax;
            if(!skipSum) this.sum();
        },
        sum(){
            let s=0,d=0,t=0,g=0, hasZero=false;
            this.items.forEach(it=>{
                const line=(+it.cantidad||0)*(+it.precio||0);
                const disc=+it.descuento||0;
                const base=Math.max(line-disc,0);
                const tax=((+it.iva_pct||0)*0.01)*base;
                const tot=base+tax;
                s+=line; d+=disc; t+=tax; g+=tot;
                if((+it.precio||0)===0 && it.product_id) hasZero=true;
                it.impuesto=tax; it.total=tot;
            });
            this.subtotal=s; this.desc_total=d; this.tax_total=t; this.grand=g;
            this.anyZeroPrice=hasZero;
        },
        fmt(n){ return Number(n||0).toFixed(2); }
    }
}
</script>

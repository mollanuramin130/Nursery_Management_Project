import { Suspense } from "react";
import { OrdersClient } from "./OrdersClient";

export default function OrdersPage() {
  return (
    <Suspense
      fallback={
        <section className="section">
          <div className="container muted">Loading orders…</div>
        </section>
      }
    >
      <OrdersClient />
    </Suspense>
  );
}

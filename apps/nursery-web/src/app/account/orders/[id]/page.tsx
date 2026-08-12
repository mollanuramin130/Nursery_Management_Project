import { Suspense } from "react";
import { OrderDetailClient } from "./OrderDetailClient";
import { Skeleton } from "@/components/ui/Skeleton";

export const metadata = { title: "Order details" };

export default function OrderDetailPage() {
  return (
    <Suspense
      fallback={
        <section className="section">
          <div className="container max-w-3xl space-y-3">
            <Skeleton className="h-10 w-64" />
            <Skeleton className="h-40 w-full" />
          </div>
        </section>
      }
    >
      <OrderDetailClient />
    </Suspense>
  );
}

"use client";

import { Component, type ErrorInfo, type ReactNode } from "react";
import { GreenLeafErrorView } from "@/components/error/GreenLeafErrorView";
import { recordClientError } from "@/lib/error-category";
import { newErrorReference, redactSecrets } from "@/lib/support";

type Props = { children: ReactNode };
type State = { error: Error | null; reference: string | null };

export class AppErrorBoundary extends Component<Props, State> {
  state: State = { error: null, reference: null };

  static getDerivedStateFromError(error: Error): Partial<State> {
    return { error, reference: newErrorReference() };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    const reference = this.state.reference ?? newErrorReference();
    recordClientError({ category: "unexpected", reference, screen: "boundary" });
    if (process.env.NODE_ENV !== "production") {
      console.warn("[GL-ERR]", reference, redactSecrets(error.message), info.componentStack);
    }
    this.setState({ error, reference });
  }

  render() {
    if (this.state.error && this.state.reference) {
      return (
        <GreenLeafErrorView
          reference={this.state.reference}
          onRetry={() => this.setState({ error: null, reference: null })}
        />
      );
    }
    return this.props.children;
  }
}

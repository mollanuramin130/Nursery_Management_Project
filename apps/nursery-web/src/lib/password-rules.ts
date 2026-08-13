/** Matches API RegisterRequest / ResetPasswordRequest Password rules. */
export function passwordRequirementErrors(password: string): string[] {
  const errors: string[] = [];
  if (password.length < 8) errors.push("At least 8 characters");
  if (!/[A-Za-z]/.test(password)) errors.push("At least one letter");
  if (!/[a-z]/.test(password) || !/[A-Z]/.test(password)) {
    errors.push("Upper and lower case letters");
  }
  if (!/\d/.test(password)) errors.push("At least one number");
  return errors;
}

export function isPasswordValid(password: string): boolean {
  return passwordRequirementErrors(password).length === 0;
}

export const PASSWORD_HINT =
  "Use 8+ characters with upper & lower case letters and a number.";

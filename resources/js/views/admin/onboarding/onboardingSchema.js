import { z } from 'zod'

export const salonSchema = z.object({
  business_name: z.string().min(1, 'Business name is required'),
  payment_type: z.enum(['Monthly', 'Quarterly', 'Yearly', 'One-time'], {
    error: 'Select a payment type',
  }),
  amount: z.coerce
    .number({ error: 'Amount must be a number' })
    .min(0, 'Amount must be 0 or more'),
  transaction_id: z.string().min(1, 'Transaction ID is required'),
  active: z.boolean().default(true),
  referral_code: z.string().optional().default(''),
  affiliate_partner_id: z.preprocess(
    (value) => (value === '' || value === undefined ? null : value),
    z.union([z.coerce.number().int().positive(), z.null()]).optional(),
  ),
})

export const branchSchema = z.object({
  id: z.number().int().positive().optional().nullable(),
  name: z.string().min(1, 'Branch name is required'),
  address_line_1: z.string().min(1, 'Address line 1 is required'),
  address_line_2: z.string().optional().default(''),
  city: z.string().min(1, 'City is required'),
  state: z.string().min(1, 'State is required'),
  pincode: z
    .string()
    .min(1, 'Pincode is required')
    .regex(/^\d{6}$/, 'Pincode must be exactly 6 digits'),
  country: z.string().min(1, 'Country is required').default('India'),
  active: z.boolean().default(true),
})

export const ownerSchema = z.object({
  id: z.number().int().positive().optional().nullable(),
  first_name: z.string().min(1, 'First name is required'),
  last_name: z.string().min(1, 'Last name is required'),
  email: z
    .string()
    .min(1, 'Email is required')
    .email('Enter a valid email address'),
  phone: z
    .string()
    .min(1, 'Phone is required')
    .regex(/^\d{10}$/, 'Phone must be exactly 10 digits'),
  password: z.string().optional().default(''),
  active: z.boolean().default(true),
})

export const serviceProductSchema = z.object({
  id: z.number().int().positive().optional().nullable(),
  service_id: z.preprocess(
    (value) => (value === '' || value === undefined ? null : value),
    z.union([z.coerce.number().int().positive(), z.null()]).optional(),
  ),
  service: z.string().optional().default(''),
  product_id: z.preprocess(
    (value) => (value === '' || value === undefined ? null : value),
    z.union([z.coerce.number().int().positive(), z.null()]).optional(),
  ),
  product: z.string().optional().default(''),
  price: z.coerce
    .number({ error: 'Price must be a number' })
    .min(0, 'Price must be 0 or more'),
  duration: z.coerce
    .number({ error: 'Duration must be a number' })
    .min(1, 'Duration must be at least 1 minute'),
  active: z.boolean().default(true),
  is_custom: z.boolean().optional().default(false),
}).superRefine((row, ctx) => {
  if (!row.service_id && !row.service?.trim()) {
    ctx.addIssue({ code: 'custom', message: 'Service is required', path: ['service'] })
  }
})

export const onboardingSchema = z.object({
  subscription_plan_id: z.coerce.number({ invalid_type_error: 'Select a subscription plan' }).min(1, 'Select a subscription plan'),
  start_trial: z.boolean().default(true),
  trial_days: z.coerce
    .number({ invalid_type_error: 'Select a trial duration' })
    .int()
    .min(0)
    .max(365),
  salon: salonSchema,
  branch: branchSchema,
  owner: ownerSchema,
  service_products: z.array(serviceProductSchema).optional().default([]),
}).superRefine((data, ctx) => {
  if (data.start_trial && Number(data.trial_days) <= 0) {
    ctx.addIssue({
      code: 'custom',
      message: 'Choose a trial duration, or turn off free trial.',
      path: ['trial_days'],
    })
  }
})

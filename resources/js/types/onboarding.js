/**
 * @typedef {Object} SalonPayload
 * @property {string} business_name
 * @property {string} payment_type - Monthly | Quarterly | Yearly | One-time
 * @property {number} amount
 * @property {string} transaction_id
 * @property {boolean} active
 * @property {string} [referral_code]
 */

/**
 * @typedef {Object} BranchPayload
 * @property {string} name
 * @property {string} address_line_1
 * @property {string} [address_line_2]
 * @property {string} city
 * @property {string} state
 * @property {string} pincode
 * @property {string} country
 * @property {boolean} active
 */

/**
 * @typedef {Object} OwnerPayload
 * @property {string} first_name
 * @property {string} last_name
 * @property {string} email
 * @property {string} phone
 * @property {string} [password]
 * @property {boolean} active
 */

/**
 * @typedef {Object} ServiceProductPayload
 * @property {string} service
 * @property {string} product
 * @property {number} price
 * @property {number} duration
 * @property {boolean} active
 */

/**
 * @typedef {Object} OnboardingPayload
 * @property {SalonPayload} salon
 * @property {BranchPayload} branch
 * @property {OwnerPayload} owner
 * @property {ServiceProductPayload[]} [service_products]
 */

/**
 * @typedef {Object} SalonResult
 * @property {number|string} id
 * @property {string} business_name
 */

/**
 * @typedef {Object} BranchResult
 * @property {number|string} id
 * @property {string} name
 */

/**
 * @typedef {Object} OwnerResult
 * @property {number|string} id
 * @property {string} first_name
 * @property {string} last_name
 * @property {string} email
 */

/**
 * @typedef {Object} OnboardingResponse
 * @property {SalonResult} salon
 * @property {BranchResult} branch
 * @property {OwnerResult} owner
 */

/**
 * Flat dot-notation field → array of error messages.
 * e.g. { "owner.email": ["Email already exists"] }
 * @typedef {Record<string, string[]>} ValidationErrors
 */

export {}

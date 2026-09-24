/**
 * Contact fields are privacy-gated by the API (`can_view_contact`).
 * Staff without permission only receive phone/email for customers they added.
 */
export function customerContactSubtitle(customer) {
  if (!customer) return 'No contact info'
  if (customer.can_view_contact === false) {
    if (customer.has_phone || customer.has_email) return 'Contact hidden'
    return 'No contact info'
  }
  return customer.email || customer.phone || 'No contact info'
}

export function customerPhoneDisplay(customer) {
  if (!customer) return null
  if (customer.can_view_contact === false) {
    return customer.has_phone ? 'Hidden' : null
  }
  return customer.phone || null
}

export function customerWhatsappDisplay(customer) {
  if (!customer) return null
  if (customer.can_view_contact === false) {
    return customer.has_whatsapp ? 'Hidden' : null
  }
  return customer.whatsapp || null
}

export function customerEmailDisplay(customer) {
  if (!customer) return null
  if (customer.can_view_contact === false) {
    return customer.has_email ? 'Hidden' : null
  }
  return customer.email || null
}

/** True when a contact exists on file (even if the viewer cannot read it). */
export function customerHasContactOnFile(customer) {
  if (!customer) return false
  return Boolean(
    customer.has_phone
    || customer.has_whatsapp
    || customer.has_email
    || customer.phone
    || customer.whatsapp
    || customer.email,
  )
}

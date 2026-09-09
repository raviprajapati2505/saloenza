import React, { useState } from 'react'
import { Star, Check, Loader2, Save } from 'lucide-react'
import BaseButton from '../ui/BaseButton.jsx'
import axios from 'axios'

export default function SkillsMatrix({ initialStaff = [], categories = ['Hair', 'Skin', 'Nails', 'Spa', 'Makeup', 'Bridal', 'Beard'] }) {
  const [staffList, setStaffList] = useState(initialStaff)
  const [savingId, setSavingId] = useState(null)
  const [saveSuccessId, setSaveSuccessId] = useState(null)

  // Toggle a skill category for a staff member
  const handleToggleSkill = (staffId, category) => {
    setStaffList((prevList) =>
      prevList.map((staff) => {
        if (staff.id !== staffId) return staff

        const skills = [...(staff.skills || [])]
        const hasSkill = skills.includes(category)
        
        let nextSkills
        let nextPrimary = staff.primarySkill

        if (hasSkill) {
          nextSkills = skills.filter((s) => s !== category)
          // If the primary skill was removed, reset it
          if (nextPrimary === category) {
            nextPrimary = nextSkills[0] || null
          }
        } else {
          nextSkills = [...skills, category]
          // If no primary skill is set, default to this newly added skill
          if (!nextPrimary) {
            nextPrimary = category
          }
        }

        return {
          ...staff,
          skills: nextSkills,
          primarySkill: nextPrimary,
        }
      })
    )
  }

  // Set primary skill category for a staff member
  const handleSetPrimary = (staffId, category) => {
    setStaffList((prevList) =>
      prevList.map((staff) => {
        if (staff.id !== staffId) return staff
        
        // Only set primary if they already have the skill
        if (!staff.skills?.includes(category)) return staff

        return {
          ...staff,
          primarySkill: staff.primarySkill === category ? null : category,
        }
      })
    )
  }

  // Save the skills configuration to the backend via PUT /v1/staff/{id}/skills
  const handleSave = async (staff) => {
    setSavingId(staff.id)
    setSaveSuccessId(null)
    try {
      await axios.put(`/v1/staff/${staff.id}/skills`, {
        skills: staff.skills || [],
        primary_skill: staff.primarySkill || null,
      })

      setSaveSuccessId(staff.id)
      setTimeout(() => {
        setSaveSuccessId(null)
      }, 3000)
    } catch (err) {
      console.error('Error saving skills matrix', err)
      alert(`Failed to save skills for ${staff.name}. Please try again.`)
    } finally {
      setSavingId(null)
    }
  }

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-50/50">
        <div>
          <h3 className="text-base font-bold text-slate-900">Skills Matrix</h3>
          <p className="text-xs text-slate-500 mt-1">Assign categories and mark primary specialties for your salon staff.</p>
        </div>
        <div className="flex items-center gap-4 text-xs font-semibold text-slate-500">
          <div className="flex items-center gap-1.5">
            <div className="w-3.5 h-3.5 border border-slate-300 rounded flex items-center justify-center bg-white"><Check className="w-2.5 h-2.5 text-brand-600" /></div>
            <span>Category Skill</span>
          </div>
          <div className="flex items-center gap-1.5">
            <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-500" />
            <span>Primary specialty</span>
          </div>
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="border-b border-slate-100 bg-slate-50/20">
              <th className="py-4 px-6 text-xs font-bold uppercase tracking-wider text-slate-400 min-w-[200px]">Staff Member</th>
              {categories.map((category) => (
                <th key={category} className="py-4 px-3 text-xs font-bold uppercase tracking-wider text-slate-400 text-center min-w-[90px]">{category}</th>
              ))}
              <th className="py-4 px-6 text-xs font-bold uppercase tracking-wider text-slate-400 text-right min-w-[120px]">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {staffList.map((staff) => (
              <tr key={staff.id} className="hover:bg-slate-50/40 transition-colors">
                {/* Staff Info Column */}
                <td className="py-4 px-6">
                  <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200/65 flex items-center justify-center font-bold text-xs text-slate-700">
                      {staff.name ? staff.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() : 'ST'}
                    </div>
                    <div>
                      <p className="text-sm font-bold text-slate-900">{staff.name}</p>
                      <p className="text-xs font-medium text-slate-500 capitalize">{staff.role}</p>
                    </div>
                  </div>
                </td>

                {/* Skill Columns */}
                {categories.map((category) => {
                  const hasSkill = staff.skills?.includes(category)
                  const isPrimary = staff.primarySkill === category

                  return (
                    <td key={category} className="py-4 px-3 text-center">
                      <div className="flex flex-col items-center justify-center gap-1.5">
                        {/* Checkbox wrapper */}
                        <button
                          type="button"
                          onClick={() => handleToggleSkill(staff.id, category)}
                          className={`w-5 h-5 rounded border transition-all flex items-center justify-center cursor-pointer ${
                            hasSkill
                              ? 'border-brand-500 bg-brand-500 text-white shadow-sm shadow-brand-500/10'
                              : 'border-slate-300 hover:border-slate-400 bg-white'
                          }`}
                        >
                          {hasSkill && <Check className="w-3.5 h-3.5 stroke-[3px]" />}
                        </button>

                        {/* Star for Primary (only visible/interactable if skill checked) */}
                        {hasSkill ? (
                          <button
                            type="button"
                            onClick={() => handleSetPrimary(staff.id, category)}
                            className="focus:outline-none transition transform hover:scale-110 active:scale-95"
                            title={isPrimary ? 'Remove primary specialty' : 'Make primary specialty'}
                          >
                            <Star
                              className={`w-4 h-4 ${
                                isPrimary
                                  ? 'fill-amber-400 text-amber-500'
                                  : 'text-slate-300 hover:text-slate-400'
                              }`}
                            />
                          </button>
                        ) : (
                          <div className="h-4" /> // placeholder spacer
                        )}
                      </div>
                    </td>
                  )
                })}

                {/* Actions Column */}
                <td className="py-4 px-6 text-right">
                  <div className="flex items-center justify-end gap-2">
                    {saveSuccessId === staff.id && (
                      <span className="text-xs font-bold text-emerald-600 animate-fade-in pr-2">Saved!</span>
                    )}
                    <BaseButton
                      size="sm"
                      variant={saveSuccessId === staff.id ? 'secondary' : 'primary'}
                      leftIcon={savingId === staff.id ? Loader2 : Save}
                      loading={savingId === staff.id}
                      onClick={() => handleSave(staff)}
                      className={saveSuccessId === staff.id ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ''}
                    >
                      {saveSuccessId === staff.id ? 'Saved' : 'Save'}
                    </BaseButton>
                  </div>
                </td>
              </tr>
            ))}

            {staffList.length === 0 && (
              <tr>
                <td colSpan={categories.length + 2} className="py-10 text-center text-sm text-slate-500">
                  No staff members loaded.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}

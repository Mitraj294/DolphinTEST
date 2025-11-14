<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-card" v-if="scheduleDetails" style="max-width: 900px; width: 90%">
      <button class="modal-close" @click="$emit('close')">&times;</button>
      <div class="modal-title">Scheduled Assessment Details</div>

      <div class="modal-desc">Details for the selected scheduled assessment.</div>
      <div class="notifications-controls">
        <div class="notifications-tabs">
          <button
            :class="['notifications-tab-btn-left', { active: tab === 'Group Wise' }]"
            @click="tab = 'Group Wise'"
          >
            Group Wise
          </button>
          <button
            :class="['notifications-tab-btn-right', { active: tab === 'Member Wise' }]"
            @click="tab = 'Member Wise'"
            min-width="320px"
          >
            Member Wise
          </button>
        </div>
      </div>
      <div v-if="tab === 'Group Wise'">
        <br />
        <div class="modal-title schedule-header" style="font-size: 20px; font-weight: 450">
          <div class="schedule-header-left">
            <div>
              <div
                class="schedule-assessment-name"
                style="
                  display: inline-block;
                  vertical-align: middle;
                  max-width: 520px;
                  margin-right: 12px;
                "
              >
                {{
                  (scheduleDetails.assessment && scheduleDetails.assessment.name) || 'Assessment'
                }}
              </div>
              -
              <div
                class="schedule-assessment-name"
                style="display: inline-block; vertical-align: middle; margin-left: 12px"
              >
                {{ displayWhen }}
              </div>
            </div>
          </div>
        </div>

        <div v-if="scheduleDetails && scheduleDetails.schedule">
          <div class="detail-row">
            <div
              class="detail-table"
              style="width: 100% !important; max-width: 800px !important; margin: 0 !important"
            >
              <div
                class="recipient-table-wrap"
                style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%"
              >
                <div class="table-scroll">
                  <table
                    v-if="groupedEmails && groupedEmails.length"
                    class="recipient-table compact"
                    style="width: 100%; min-width: 500px"
                  >
                    <TableHeader
                      :columns="[
                        { label: 'Group', key: 'group', minWidth: '200px' },
                        { label: 'Members', key: 'members', minWidth: '200px' },
                        { label: 'Email', key: 'email', minWidth: '200px' },
                        {
                          label: 'Member Roles',
                          key: 'member_roles',
                          minWidth: '200px',
                        },
                      ]"
                    />
                    <tbody>
                      <template v-for="(g, gi) in groupedEmails" :key="'group-' + gi">
                        <tr v-for="(e, ei) in g.items" :key="'email-' + gi + '-' + ei">
                          <td v-if="ei === 0" :rowspan="g.items.length" class="group-cell">
                            {{ g.name || 'Ungrouped' }}
                          </td>
                          <td>
                            {{
                              e.displayName ||
                              (e.member_id && memberDetailMap[e.member_id]
                                ? memberDetailMap[e.member_id].name
                                : e.recipient_email || e.email || e.to) ||
                              'Unknown'
                            }}
                          </td>
                          <td>
                            {{
                              e.displayEmail ||
                              (e.member_id && memberDetailMap[e.member_id]
                                ? memberDetailMap[e.member_id].email
                                : e.recipient_email || e.email || e.to) ||
                              ''
                            }}
                          </td>
                          <td>
                            {{
                              e.displayRoles ||
                              (e.member_id && memberDetailMap[e.member_id]
                                ? memberDetailMap[e.member_id].rolesDisplay
                                : Array.isArray(e.memberRoles) && e.memberRoles.length
                                  ? e.memberRoles.map((r) => (r && r.name) || r).join(', ')
                                  : Array.isArray(e.member_role_ids) && e.member_role_ids.length
                                    ? e.member_role_ids.map((r) => (r && r.name) || r).join(', ')
                                    : e.member_role) ||
                              'User'
                            }}
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                  <div v-else class="no-data">
                    <em>No groups found for this schedule.</em>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else>
          <em>No schedule details found.</em>
        </div>
      </div>

      <div v-else-if="tab === 'Member Wise'">
        <br />
        <div class="modal-title schedule-header" style="font-size: 20px; font-weight: 450">
          <div class="schedule-header-left">
            <div>
              <div
                class="schedule-assessment-name"
                style="
                  display: inline-block;
                  vertical-align: middle;
                  max-width: 520px;
                  margin-right: 12px;
                "
              >
                {{
                  (scheduleDetails.assessment && scheduleDetails.assessment.name) || 'Assessment'
                }}
              </div>
              -
              <div
                class="schedule-assessment-name"
                style="display: inline-block; vertical-align: middle; margin-left: 12px"
              >
                {{ displayWhen }}
              </div>
            </div>
          </div>
        </div>
        <div class="detail-row">
          <div
            class="detail-table"
            style="width: 100% !important; max-width: 800px !important; margin: 0 !important"
          >
            <div
              class="recipient-table-wrap"
              style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%"
            >
              <div class="table-scroll">
                <table
                  v-if="memberWiseRows && memberWiseRows.length"
                  class="recipient-table compact"
                  style="width: 100%; min-width: 500px"
                >
                  <TableHeader
                    :columns="[
                      {
                        label: 'Member Name',
                        key: 'name',
                        minWidth: '200px',
                      },
                      { label: 'Email', key: 'email', minWidth: '200px' },
                      { label: 'Groups', key: 'groups', minWidth: '200px' },
                      {
                        label: 'Member Roles',
                        key: 'rolesDisplay',
                        minWidth: '200px',
                      },
                    ]"
                  />
                  <tbody>
                    <tr v-for="m in memberWiseRows" :key="'memberwise-' + m.id">
                      <td>{{ m.name }}</td>
                      <td>{{ m.email }}</td>
                      <td>
                        {{ m.groups && m.groups.length ? m.groups.join(', ') : '' }}
                      </td>
                      <td>{{ m.rolesDisplay || 'User' }}</td>
                    </tr>
                  </tbody>
                </table>
                <div v-else class="no-data">
                  <em>No members found for Member Wise view.</em>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import TableHeader from '@/components/Common/Common_UI/TableHeader.vue';

export default {
  name: 'ScheduleDetailsModal',
  components: { TableHeader },

  props: {
    scheduleDetails: {
      type: Object,
      default: null,
    },
    allGroups: {
      type: Array,
      default: () => [],
    },
    allMembers: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['close'],
  data() {
    return {
      tab: 'Group Wise',
    };
  },
  computed: {
    memberDetailMap() {
      const map = {};
      this.fillMemberMapFromEnhanced(map);
      this.fillMemberMapFromGroups(map);
      this.fillMemberMapFromAllMembers(map);
      return map;
    },
    filteredEmails() {
      if (!this.scheduleDetails || !this.scheduleDetails.emails) return [];
      const schedule = this.scheduleDetails.schedule;
      if (!schedule) return [];
      const assessmentId = this.scheduleDetails.assessment && this.scheduleDetails.assessment.id;
      if (!assessmentId) return [];
      return (this.scheduleDetails.emails || []).filter(
        (e) => e && e.assessment_id === assessmentId
      );
    },
    // Safe display string for the header date/time
    displayWhen() {
      const schedule = (this.scheduleDetails && this.scheduleDetails.schedule) || null;
      if (!schedule) return '';
      return this.formatLocalDateTime(schedule.date, schedule.time);
    },
    groupedEmails() {
      const normalizedGroups =
        (this.scheduleDetails && this.scheduleDetails.groups_with_members) || [];
      if (normalizedGroups.length) {
        return normalizedGroups.map((group) => ({
          id: group.id || group.name || 'ungrouped',
          name: group.name || `Group ${group.id || 'Unknown'}`,
          items: this.buildGroupMemberItems(group),
        }));
      }

      const list = this.filteredEmails || [];
      const schedule = (this.scheduleDetails && this.scheduleDetails.schedule) || null;
      const scheduleGroupIds = schedule ? this.parseIdArray(schedule.group_ids) : [];
      return scheduleGroupIds.length
        ? this.groupedByScheduleGroupIds(list, scheduleGroupIds)
        : this.groupedByExistingList(list);
    },
    memberWiseRows() {
      const normalized = this.scheduleDetails;
      if (!normalized) return [];

      const memberIds = this.parseArrayFieldGeneric(
        normalized.schedule ? normalized.schedule.member_ids : normalized.member_ids
      );
      const idSet = new Set(memberIds);

      if (!idSet.size && Array.isArray(normalized.members_with_details)) {
        for (const member of normalized.members_with_details) {
          const mid = Number(member.id);
          if (!Number.isNaN(mid)) idSet.add(mid);
        }
      }

      if (!idSet.size && Array.isArray(this.allMembers)) {
        for (const member of this.allMembers) {
          const mid = Number(member.id);
          if (!Number.isNaN(mid)) idSet.add(mid);
        }
      }

      if (!idSet.size) return [];

      return Array.from(idSet).map((mid) => {
        const detail = this.memberDetailMap[mid] || {
          name: `Member ${mid}`,
          email: '',
          rolesDisplay: '',
        };
        return {
          id: mid,
          name: detail.name || `Member ${mid}`,
          email: detail.email || '',
          groups: this.memberGroupNames(mid),
          rolesDisplay: detail.rolesDisplay || '',
        };
      });
    },
  },
  methods: {
    // ---- Helpers for memberDetailMap ----
    fillMemberMapFromEnhanced(map) {
      const details = this.scheduleDetails;
      const list = details && details.members_with_details;
      if (!Array.isArray(list) || list.length === 0) return;
      for (const m of list) {
        this.assignMemberDetail(map, m);
      }
    },
    fillMemberMapFromGroups(map) {
      const details = this.scheduleDetails;
      const groups = details && details.groups_with_members;
      if (!Array.isArray(groups) || groups.length === 0) return;
      for (const group of groups) {
        this.fillGroupMembersIntoMap(group, map);
      }
    },
    fillGroupMembersIntoMap(group, map) {
      if (!group || !Array.isArray(group.members) || !group.members.length) return;
      for (const m of group.members) {
        this.assignMemberDetail(map, m);
      }
    },
    fillMemberMapFromAllMembers(map) {
      const list = this.allMembers || [];
      for (const m of list) {
        this.assignMemberDetail(map, m);
      }
    },
    buildMemberDisplayInfo(member) {
      if (!member) {
        return { name: 'Unknown', email: '', memberRoles: [], rolesDisplay: '' };
      }
      const first = (member.first_name || member.name || '').toString().trim();
      const last = (member.last_name || '').toString().trim();
      let name = first;
      if (last) name = name ? `${name} ${last}` : last;
      if (!name) name = member.email || `Member ${member.id || member.member_id || ''}`;

      const memberRoles = Array.isArray(member.member_roles)
        ? member.member_roles.map((r) => (typeof r === 'object' ? r : { id: r, name: String(r) }))
        : [];
      let rolesDisplay = memberRoles.length ? memberRoles.map((r) => r.name || r).join(', ') : '';
      if (!rolesDisplay) {
        const fallbackRole = member.member_role || member.role || '';
        rolesDisplay = fallbackRole ? String(fallbackRole) : '';
      }

      return {
        name,
        email: member.email || member.recipient_email || '',
        memberRoles,
        rolesDisplay,
      };
    },

    assignMemberDetail(map, member) {
      const memberId = Number(member.id || member.member_id || member.user_id);
      if (Number.isNaN(memberId)) return;
      const info = this.buildMemberDisplayInfo(member);
      const existing = map[memberId] || {};
      map[memberId] = {
        name: existing.name || info.name,
        email: existing.email || info.email,
        memberRoles: info.memberRoles.length > 0 ? info.memberRoles : existing.memberRoles || [],
        rolesDisplay: info.rolesDisplay || existing.rolesDisplay || '',
      };
    },

    memberGroupNames(memberId) {
      const normalizedGroups =
        (this.scheduleDetails && this.scheduleDetails.groups_with_members) || [];
      const numericId = Number(memberId);
      const matched = new Set();
      if (!Number.isNaN(numericId)) {
        for (const group of normalizedGroups) {
          const members = Array.isArray(group.members) ? group.members : [];
          if (
            members.some(
              (member) => Number(member.id || member.member_id || member.user_id) === numericId
            )
          ) {
            const label = group.name || `Group ${group.id}`;
            if (label) matched.add(label);
          }
        }
      }
      if (matched.size === 0 && Array.isArray(this.allGroups)) {
        for (const group of this.allGroups) {
          const members = Array.isArray(group.members) ? group.members : [];
          if (
            members.some((member) => Number(member.id || member.member_id || member) === numericId)
          ) {
            const label = group.name || `Group ${group.id}`;
            if (label) matched.add(label);
          }
        }
      }
      return Array.from(matched);
    },

    buildGroupMemberItems(group) {
      const members = Array.isArray(group.members) ? group.members : [];
      if (!members.length) {
        return [
          {
            member_id: null,
            displayName: 'No members assigned',
            displayEmail: '',
            displayRoles: '',
            memberRoles: [],
            member_role_ids: [],
            member_role: '',
            recipient_email: '',
          },
        ];
      }
      const items = [];
      for (const member of members) {
        const normalized = this.normalizeGroupMemberItem(member);
        if (normalized) items.push(normalized);
      }
      return items;
    },

    normalizeGroupMemberItem(member) {
      if (!member) return null;
      const memberId = Number(member.id || member.member_id || member.user_id);
      const detail = memberId ? this.memberDetailMap[memberId] : null;
      const displayName =
        (detail && detail.name) ||
        this.buildMemberDisplayName(member) ||
        member.email ||
        member.recipient_email ||
        `Member ${memberId || 'Unknown'}`;
      const displayEmail = (detail && detail.email) || member.email || member.recipient_email || '';
      let displayRoles = detail && detail.rolesDisplay;
      if (!displayRoles) {
        if (Array.isArray(member.member_roles) && member.member_roles.length) {
          displayRoles = member.member_roles.map((r) => (r && r.name) || r).join(', ');
        } else if (member.member_role) {
          displayRoles = member.member_role;
        } else {
          displayRoles = '';
        }
      }
      return {
        member_id: memberId,
        displayName,
        displayEmail,
        displayRoles,
        memberRoles: member.member_roles,
        member_role_ids: member.member_role_ids,
        member_role: member.member_role,
        recipient_email: member.email || member.recipient_email || '',
      };
    },

    buildMemberDisplayName(member) {
      if (!member) return '';
      const first = (member.first_name || member.name || '').toString().trim();
      const last = (member.last_name || '').toString().trim();
      let name = first;
      if (last) name = name ? `${name} ${last}` : last;
      if (!name) {
        name = member.email || member.recipient_email || '';
      }
      return name;
    },

    // ---- Helpers for groupedEmails ----
    parseIdArray(v) {
      if (!v) return [];
      if (Array.isArray(v)) return v.map(Number);
      try {
        const p = JSON.parse(v);
        return Array.isArray(p) ? p.map(Number) : [];
      } catch (e) {
        // Fallback: strip brackets and whitespace then split
        console.debug && console.debug('parseIdArray JSON parse failed', e);
        const cleaned = v.toString().replaceAll('[', '').replaceAll(']', '').replaceAll(/\s+/g, '');
        return cleaned.split(',').filter(Boolean).map(Number);
      }
    },
    parseArrayFieldGeneric(v) {
      if (!v) return [];
      if (Array.isArray(v)) return v.map(Number).filter(Boolean);
      try {
        const parsed = typeof v === 'string' ? JSON.parse(v) : v;
        if (Array.isArray(parsed)) return parsed.map(Number).filter(Boolean);
      } catch (err) {
        if (console && typeof console.debug === 'function')
          console.debug('parseArrayFieldGeneric parse failed', err);
      }
      const cleaned = String(v).replaceAll('[', '').replaceAll(']', '').replaceAll(/\s+/g, '');
      return cleaned.split(',').filter(Boolean).map(Number);
    },
    groupedByScheduleGroupIds(list, scheduleGroupIds) {
      const map = new Map();
      for (const gid of scheduleGroupIds) {
        const gobj = (this.allGroups || []).find((gg) => Number(gg.id) === gid);
        const gname = (gobj && (gobj.name || gobj.group)) || `Group ${gid}`;
        const items = this.buildItemsForGroup(list, gid);
        const memberIds = this.getGroupMemberIds(gobj, gid);
        for (const mid of memberIds) {
          if (!items.some((i) => Number(i.member_id) === mid)) items.push({ member_id: mid });
        }
        map.set(gid, { id: gid, name: gname, items: items.filter(Boolean) });
      }
      return Array.from(map.values());
    },
    buildItemsForGroup(list, gid) {
      const items = [];
      for (const e of list) {
        const egids = new Set();
        if (e.group_id) egids.add(Number(e.group_id));
        if (e.group_ids) for (const id of this.parseIdArray(e.group_ids)) egids.add(id);
        if (egids.has(gid)) items.push(e);
      }
      return items;
    },
    getGroupMemberIds(gobj, gid) {
      if (gobj && Array.isArray(gobj.members) && gobj.members.length) {
        return gobj.members.map((m) => Number(m.id || m.member_id || m));
      }
      return (this.allMembers || [])
        .filter((m) => {
          const mgids = Array.isArray(m.group_ids) ? m.group_ids : [];
          return mgids.some((mgid) => Number(mgid) === gid) || Number(m.group_id) === gid;
        })
        .map((m) => Number(m.id));
    },
    groupedByExistingList(list) {
      const map = new Map();
      for (const e of list) {
        const gid = e.group_id || e.group || 'ungrouped';
        const gname =
          e.group_name || e.group || (gid === 'ungrouped' ? 'Ungrouped' : `Group ${gid}`);
        if (!map.has(gid)) map.set(gid, { id: gid, name: gname, items: [] });
        map.get(gid).items.push(e);
      }
      for (const v of map.values()) {
        v.items = (v.items || []).filter(Boolean);
      }
      return Array.from(map.values());
    },
    formatLocalDateTime(dateStr, timeStr) {
      if (!dateStr) return '';
      try {
        const { year, month, day } = this.parseDateString(dateStr);
        const { hour, minute, second } = this.parseTimeString(timeStr);
        const dt = new Date(year, month, day, hour, minute, second);
        return Number.isNaN(dt.getTime())
          ? `${dateStr} ${timeStr || ''}`.trim()
          : this.formatDisplayTime(dt);
      } catch {
        return `${dateStr} ${timeStr || ''}`.trim();
      }
    },
    parseDateString(dateStr) {
      const dmatch = (dateStr || '')
        .toString()
        .trim()
        .match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (dmatch) {
        return {
          year: Number(dmatch[1]),
          month: Number(dmatch[2]) - 1,
          day: Number(dmatch[3]),
        };
      } else {
        const alt = new Date(dateStr);
        if (Number.isNaN(alt.getTime())) throw new Error('Invalid date');
        return {
          year: alt.getFullYear(),
          month: alt.getMonth(),
          day: alt.getDate(),
        };
      }
    },
    parseTimeString(timeStr) {
      const tmatch = (timeStr || '')
        .toString()
        .trim()
        .match(/^(\d{2}):(\d{2})(?::(\d{2}))?$/);
      if (tmatch) {
        return {
          hour: Number(tmatch[1]),
          minute: Number(tmatch[2]),
          second: Number(tmatch[3] || 0),
        };
      }
      return { hour: 0, minute: 0, second: 0 };
    },
    formatDisplayTime(dt) {
      const dayNum = String(dt.getDate()).padStart(2, '0');
      const months = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
      ];
      const mon = months[dt.getMonth()];
      const yr = dt.getFullYear();
      let hr = dt.getHours();
      const min = String(dt.getMinutes()).padStart(2, '0');
      const ampm = hr >= 12 ? 'PM' : 'AM';
      hr = hr % 12 || 12; // Convert hour to 12-hour format
      return `${dayNum} ${mon}, ${yr} ${hr}:${min} ${ampm}`;
    },
  },
};
</script>

<style scoped>
.notifications-controls {
  display: flex;
  flex-direction: row-reverse;
  margin-bottom: 24px;
  background: #fff;
  border-top-left-radius: 24px;
  border-top-right-radius: 24px;
  box-sizing: border-box;
}

.notifications-tabs {
  display: flex;
  border-radius: 32px;
  background: #f8f8f8;
  overflow: hidden;
  min-width: 240px;
  height: 36px;
}

.notifications-tab-btn-left,
.notifications-tab-btn-right {
  border: none;
  min-width: 150px;
  border-radius: 32px;
  outline: none;
  background: #f8f8f8;
  color: #0f0f0f;
  font-family: 'Helvetica Neue LT Std', Arial, sans-serif;
  font-size: 18px;
  font-weight: 400;
  line-height: 26px;
  letter-spacing: 0.02em;
  flex: 1;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition:
    background 0.18s,
    color 0.18s,
    border 0.18s,
    font-weight 0.18s;
  cursor: pointer;
  box-sizing: border-box;
}

.notifications-tab-btn-left.active {
  background: #f6f6f6;
  border: 1.5px solid #dcdcdc;
  border-radius: 32px 0 0 32px;
  color: #0f0f0f;
  font-weight: 500;
  z-index: 1;
}

.notifications-tab-btn-right.active {
  background: #f6f6f6;
  border: 1.5px solid #dcdcdc;
  border-radius: 0 32px 32px 0;
  color: #0f0f0f;
  font-weight: 500;
  z-index: 1;
}
</style>

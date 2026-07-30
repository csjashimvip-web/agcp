import { apiFetch } from "./auth-api";
export type RuleRecord={id:string;name:string;slug:string;scope:"pricing"|"fraud"|"operations";status:string;priority:number;stop_on_match:boolean;published_version:number|null;latest_version:number|null;condition_mode:"all"|"any";conditions:Array<{field:string;operator:string;value:unknown}>;actions:Array<{type:string;value:unknown}>};
export type FraudAssessment={id:string;score:number;level:string;decision:string;status:string;review_note:string|null;created_at:string;user?:{id:string;name:string;email:string}|null;order?:{id:string;number:string;total_minor:number;currency:string;fulfillment_status:string}|null;signals:Array<{id:string;code:string;score:number;severity:string;message:string}>};
type Collection<T>={data:T[];meta?:unknown;links?:unknown};
export const rulesApi={
 rules:()=>apiFetch<Collection<RuleRecord>>("/api/v1/admin/rules"),
 create:(input:Record<string,unknown>)=>apiFetch<{data:RuleRecord}>("/api/v1/admin/rules",{method:"POST",body:JSON.stringify(input)}),
 publish:(id:string)=>apiFetch<{data:RuleRecord}>(`/api/v1/admin/rules/${id}/publish`,{method:"POST"}),
 pause:(id:string)=>apiFetch<{data:RuleRecord}>(`/api/v1/admin/rules/${id}/pause`,{method:"POST"}),
 assessments:()=>apiFetch<Collection<FraudAssessment>>("/api/v1/admin/fraud/assessments"),
 approve:(id:string,note?:string)=>apiFetch<{data:FraudAssessment}>(`/api/v1/admin/fraud/assessments/${id}/approve`,{method:"POST",body:JSON.stringify({note})}),
 reject:(id:string,note?:string)=>apiFetch<{data:FraudAssessment}>(`/api/v1/admin/fraud/assessments/${id}/reject`,{method:"POST",body:JSON.stringify({note})}),
};

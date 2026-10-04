import type {
    IncomingShipmentResult,
    ProductTemperatureEvidence,
    ProductionReadiness,
    StoredBatchAssessment,
} from '@/types/integration';

export type ActionKey =
    | 'RUN_AS_PLANNED'
    | 'EXPEDITE'
    | 'BUFFER'
    | 'REROUTE'
    | 'QUARANTINE';

export type Role = 'operator' | 'logistics' | 'qa';

export type Verdict = 'yes' | 'no' | 'at_risk' | 'unknown';

export type EvidenceKind = 'real' | 'simulated' | 'unverified';

export type Answer = {
    question: string;
    verdict: Verdict;
    detail: string;
};

export type Reason = {
    n: number;
    text: string;
    kind: EvidenceKind;
    source: string;
    at: string;
    signal?: string;
};

export type ActionOption = {
    action: ActionKey;
    label: string;
    pMissSlot: number;
    expDelayMin: number;
    pExcursion: number;
    stockAfter: number;
    recommended: boolean;
    note: string;
};

export type JourneyStop = {
    id: string;
    label: string;
    position: number;
    state: 'done' | 'current' | 'blocked' | 'pending';
};

export type TemperaturePoint = {
    at: string;
    c: number;
};

export type Exposure = {
    from: string;
    to: string;
    label: string;
};

export type Signal = {
    title: string;
    unit: string;
    points: { at: string; v: number }[];
    threshold?: { v: number; label: string };
};

export type ShipmentStatus = 'in_transit' | 'suspended' | 'arrived' | 'closed';

export type ShipmentDecision = {
    verdict: 'APPROVE' | 'OVERRIDE';
    action: ActionKey;
    role: Role;
    by: string;
    reason: string;
    decidedAt: string;
};

export type ShipmentListItem = {
    id: number;
    lot: string;
    scenario: string | null;
    reactor: string;
    chargeAt: string;
    timezone: string;
    status: ShipmentStatus;
    currentSegment: string;
    routeProgress: number;
    recommendedAction: ActionKey | null;
    approverRole: Role | null;
    answers: { arrival: Answer; quality: Answer } | null;
    decision: ShipmentDecision | null;
};

export type ShipmentConsole = {
    id: number;
    lot: string;
    scenario: string | null;
    simulated: boolean;
    reactor: string;
    chargeAt: string;
    asOf: string;
    timezone: string;
    status: ShipmentStatus;
    assessmentId: number;
    answers: { arrival: Answer; quality: Answer };
    recommendation: {
        action: ActionKey;
        title: string;
        summary: string;
        glance: string;
        approver: Role;
    };
    reasons: Reason[];
    options: ActionOption[];
    wouldChangeIf: string[];
    journey: { stops: JourneyStop[]; progress: number };
    temperature: {
        band: [number, number];
        points: TemperaturePoint[];
        exposures: Exposure[];
    };
    budget: { usedFraction: number; budgetMin: number };
    signals: Record<string, Signal>;
    log: ShipmentDecision[];
};

export type BatchDecisionStatus = 'pending' | 'accepted' | 'denied';

export type BatchDecision = {
    id: number;
    assessmentId: string;
    modelAction: ActionKey | null;
    action: ActionKey;
    requiredRole: Role;
    status: BatchDecisionStatus;
    submittedBy: string;
    submittedReason: string;
    submittedAt: string;
    reviewedBy: string | null;
    reviewReason: string | null;
    nextAction: string | null;
    responsibilityConfirmed: boolean;
    reviewedAt: string | null;
};

export type BatchRecommendation = {
    action: ActionKey | null;
    reason: string;
    approver: Role;
    qaReviewRequired: boolean;
};

export type BatchListItem = {
    batchId: string;
    datasetId: string;
    label: string;
    reactor: string | null;
    chargeAt: string;
    asOf: string;
    retrospective: boolean;
    answers: { arrival: Answer; quality: Answer };
    recommendation: BatchRecommendation;
    decision: BatchDecision | null;
};

export type BatchTemperatureSeries = {
    shipmentId: string;
    points: TemperaturePoint[];
    exposures: Exposure[];
};

export type BatchBriefing = {
    headline: string;
    summary: string;
    qaNote: string | null;
    source: 'llm' | 'template';
    model: string | null;
};

export type BatchConsole = Omit<BatchListItem, 'decision'> & {
    briefing: BatchBriefing;
    assessmentId: string;
    production: ProductionReadiness;
    incoming: IncomingShipmentResult[];
    temperatures: ProductTemperatureEvidence[];
    temperatureSeries: BatchTemperatureSeries[];
    limitations: string[];
    assessment: StoredBatchAssessment;
    decisions: BatchDecision[];
    activeDecision: BatchDecision | null;
};

export type BatchReviewItem = {
    id: number;
    batchId: string;
    label: string;
    action: ActionKey;
    modelAction: ActionKey | null;
    submittedBy: string;
    submittedReason: string;
    submittedAt: string;
    chargeAt: string | null;
    status: BatchDecisionStatus;
    reviewReason: string | null;
    nextAction: string | null;
    reviewedAt: string | null;
};

export type RoleOverview = {
    role: Role;
    engineUnavailable: boolean;
    needsDecision: BatchListItem[];
    waitingOnOthers: BatchListItem[];
    nextCharges: BatchListItem[];
    reviewQueue: BatchReviewItem[];
    watchlist: BatchListItem[];
    recentlyReviewed: BatchReviewItem[];
};

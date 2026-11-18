<x-layout title="Dashboard HR">
<x-dashboard-overview :employees="$active" :present="$present" :late="$late" :absent="$absent" :year="$year" :monthly="$monthly" :notifications="$notifications" />
<div class="dashboard-shortcuts">
</div>
</x-layout>

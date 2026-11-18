<x-layout title="Direktori Karyawan">
<div class="compact-list">
<x-list-toolbar title="Direktori Karyawan" breadcrumb="Karyawan" :dashboard="route('admin.home')" :action="route('admin.employees.index')" :paginator="$employees" :filters="['q', 'division_id', 'active']" unit="karyawan" description="Pengelolaan profil merupakan kewenangan HR.">
<x-directory-filter-fields :divisions="$divisions"/>
</x-list-toolbar>
<x-employee-table :employees="$employees" role="admin"/>
<x-list-pagination :paginator="$employees"/>
</div>
</x-layout>

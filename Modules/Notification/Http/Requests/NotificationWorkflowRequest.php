<?php

namespace Modules\Notification\Http\Requests;

//   طلب التحقق من صحة مدخلات قاعدة أتمتة الإشعارات والخطوات التابعة لها.

use Illuminate\Foundation\Http\FormRequest;

class NotificationWorkflowRequest extends FormRequest
{
    protected function prepareForValidation(): void {
        $steps=$this->input('steps',[]);
        if(is_array($steps))foreach($steps as &$step)if(is_array($step)&&is_string($step['channel']??null))$step['channel']=$step['channel']==='both'?['email','whatsapp']:[$step['channel']];
        $this->merge(['steps'=>$steps]);
    }
    public function authorize()
    {
        return true;
    }
    public function withValidator($validator): void {
        $validator->after(function($validator){
            if($validator->errors()->any())return;
            $companyId=(int)$this->user()?->active_company_id;
            foreach($this->input('steps',[]) as $index=>$step){
                $template=\Modules\Notification\Models\NotificationTemplate::withoutGlobalScopes()->whereNull('deleted_at')->find($step['template_id']);
                if(!$template||((int)$template->company_id!==$companyId&&!($template->is_global&&($template->company_id===null||(int)$template->company_id===(int)config('app.master_company_id',1)))))
                    $validator->errors()->add('steps.'.$index.'.template_id','القالب غير متاح لشركتك.');
            }
        });
    }

    public function rules()
    {
        return [
            'event_type' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
            'is_global' => [
                'nullable',
                'boolean',
                function ($attribute, $value, $fail) {
                    if ($value && (!auth()->user() || !auth()->user()->hasPermissionTo(perm_key('admin.super')))) {
                        $fail('غير مسموح لغير المسؤولين الخارقين تفعيل الإعدادات العامة.');
                    }
                }
            ],
            'steps' => 'required|array|min:1',
            'steps.*.id' => 'nullable|integer',
            'steps.*.step_number' => 'required|integer|min:1',
            'steps.*.delay_days' => str_starts_with((string)$this->input('event_type'),'store.')?'required|integer|min:0|max:365':'required|integer|min:-365|max:365',
            'steps.*.channel' => 'required|array|min:1',
            'steps.*.channel.*' => str_starts_with((string)$this->input('event_type'),'store.')?'required|in:email':'required|in:email,whatsapp',
            'steps.*.template_id' => 'required|integer|exists:notification_templates,id',
            'steps.*.is_active' => 'nullable|boolean',
        ];
    }
}

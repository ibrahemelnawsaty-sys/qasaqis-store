{{-- زر CTA قابل لإعادة الاستخدام — bulletproof (VML لـ Outlook، fallback للبقية).
     المتغيّرات: $url (إلزامي) · $label (إلزامي). --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" class="qa-btn" style="margin:24px auto 6px;">
    <tr>
        <td align="center" bgcolor="#6E2FB0" style="border-radius:999px;background:#6E2FB0;background:linear-gradient(120deg,#6E2FB0,#EC4E96);">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:280px;" arcsize="50%" strokecolor="#6E2FB0" fillcolor="#6E2FB0"><w:anchorlock/><center style="color:#ffffff;font-family:Tahoma,Arial,sans-serif;font-size:15px;font-weight:800;">{{ $label }}</center></v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-- --><a href="{{ $url }}" style="display:inline-block;padding:14px 36px;font-size:15px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:999px;">{{ $label }}</a><!--<![endif]-->
        </td>
    </tr>
</table>
